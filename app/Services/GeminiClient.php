<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien tipis untuk Google Gemini (generateContent).
 *
 * TANGGUNG JAWAB
 * --------------
 * Hanya mengirim prompt dan mengembalikan JSON yang sudah di-decode.
 * Seluruh validasi semantik (mis. company_id harus berasal dari daftar
 * kandidat) dilakukan oleh pemanggil — AI tidak boleh "mengarang" data.
 *
 * Kegagalan jaringan/kredensial TIDAK melempar exception ke pengguna;
 * method mengembalikan null dan pemanggil memakai perhitungan lokal
 * sebagai fallback. Dengan begitu halaman rekomendasi tetap berfungsi
 * meski kuota/API key bermasalah.
 */
class GeminiClient
{
    /**
     * Apakah API key sudah dikonfigurasi.
     */
    public function enabled(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Nama model yang dipakai (untuk ditampilkan/di-log).
     */
    public function model(): string
    {
        return (string) config('services.gemini.model', 'gemini-3.8-flash');
    }

    /**
     * Kirim prompt teks dan harapkan balasan berupa objek JSON.
     *
     * @return array<string, mixed>|null
     */
    public function generateJson(string $prompt, ?string $systemInstruction = null): ?array
    {
        return $this->request(
            [['text' => $prompt]],
            $systemInstruction,
            (int) config('services.gemini.timeout', 45),
        );
    }

    /**
     * Kirim dokumen PDF (inline data) + prompt ke Gemini.
     *
     * Dipakai ekstraksi PDF agar Gemini bisa membaca tata letak dokumen,
     * termasuk PDF yang teksnya sulit diparsing. File hanya dibaca dari
     * disk sementara dan TIDAK pernah disimpan/diunggah ke storage publik.
     *
     * @return array<string, mixed>|null
     */
    public function generateJsonFromPdf(
        string $absolutePath,
        string $prompt,
        ?string $systemInstruction = null,
        string $mimeType = 'application/pdf',
    ): ?array {
        if (! $this->enabled() || ! is_file($absolutePath)) {
            return null;
        }

        $bytes = @file_get_contents($absolutePath);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        return $this->request(
            [
                [
                    'inlineData' => [
                        'mimeType' => $mimeType,
                        'data' => base64_encode($bytes),
                    ],
                ],
                ['text' => $prompt],
            ],
            $systemInstruction,
            (int) config('services.gemini.pdf_timeout', 90),
        );
    }

    /**
     * Kirim request generateContent dan decode JSON hasilnya.
     *
     * API key dikirim lewat header `x-goog-api-key` (bukan query string)
     * supaya tidak muncul di URL, access log server, atau pesan error.
     *
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<string, mixed>|null
     */
    private function request(array $parts, ?string $systemInstruction, int $timeout): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $endpoint = rtrim((string) config('services.gemini.endpoint'), '/');
        $url = $endpoint.'/models/'.$this->model().':generateContent';

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        if (filled($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        try {
            $response = Http::withOptions([
                'verify' => (bool) config('services.gemini.verify_ssl', true),
            ])
                ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->timeout(max(10, $timeout))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('Gemini menolak permintaan.', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $text = $this->extractText($response->json());

            if ($text === null) {
                return null;
            }

            $decoded = json_decode($this->stripCodeFence($text), true);

            return is_array($decoded) ? $decoded : null;
        } catch (ConnectionException $e) {
            Log::warning('Koneksi ke Gemini gagal: '.$e->getMessage());

            return null;
        } catch (Throwable $e) {
            Log::warning('Gemini error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Ambil gabungan teks dari struktur response Gemini.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function extractText(?array $body): ?string
    {
        $parts = $body['candidates'][0]['content']['parts'] ?? null;

        if (! is_array($parts)) {
            return null;
        }

        $text = '';

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        $text = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * Gemini kadang membungkus JSON dengan ```json ... ```.
     */
    private function stripCodeFence(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $matches) === 1) {
            return $matches[1];
        }

        return $text;
    }
}
