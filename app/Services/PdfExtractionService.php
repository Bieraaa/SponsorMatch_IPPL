<?php

namespace App\Services;

use App\Models\Kategori;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Ekstraksi isi PDF proposal / campaign dengan Gemini.
 *
 * ALUR
 * ----
 * 1. Teks PDF dibaca lebih dulu dengan `smalot/pdfparser`. Untuk PDF
 *    berbasis teks, teks inilah yang dikirim ke Gemini (lebih murah dan
 *    cepat daripada mengirim seluruh file).
 * 2. Bila PDF tidak punya lapisan teks (hasil scan), file dikirim sebagai
 *    inline data supaya Gemini tetap bisa membacanya.
 * 3. Respons Gemini divalidasi ketat: hanya field yang dikenal, tipe data
 *    diperiksa, kategori dicocokkan dengan tabel `kategori`, dan tanggal
 *    dinormalisasi ke `Y-m-d`. Nilai yang tidak ada di dokumen tidak
 *    ditambahkan.
 * 4. Untuk proposal, bila Gemini tidak tersedia/gagal, dipakai fallback
 *    regex lama (`PdfProposalExtractor`) agar fitur lama tetap berjalan.
 *
 * Service ini TIDAK menyimpan apa pun. File upload hanya dibaca dari
 * lokasi sementara PHP dan hasilnya dikembalikan ke controller.
 */
class PdfExtractionService
{
    /** Batas teks yang dikirim ke Gemini. */
    private const MAX_TEXT = 60000;

    /** Nilai `needType` yang diterima form proposal. */
    private const NEED_TYPES = ['Dana', 'Produk', 'Dana & produk', 'Jasa'];

    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly PdfProposalExtractor $regexExtractor,
    ) {}

    /**
     * Ekstrak proposal EO.
     *
     * @return array{fields: array<string, mixed>, filled: array<int, string>, textLength: int, source: string}
     *
     * @throws RuntimeException kode EMPTY_TEXT / AI_UNAVAILABLE
     */
    public function extractProposal(string $absolutePath): array
    {
        $text = $this->readText($absolutePath);

        $ai = $this->extractWithAi($absolutePath, $text, 'proposal');

        if ($ai !== null) {
            $fields = $this->proposalFields($ai);

            if ($fields !== []) {
                return $this->result($fields, $text, 'gemini');
            }
        }

        // Fallback: pembacaan regex yang sudah dipakai sebelum integrasi AI.
        $result = $this->regexExtractor->extract($absolutePath);
        $result['source'] = 'regex';

        return $result;
    }

    /**
     * Ekstrak campaign perusahaan.
     *
     * @return array{fields: array<string, mixed>, filled: array<int, string>, textLength: int, source: string}
     *
     * @throws RuntimeException kode AI_UNAVAILABLE
     */
    public function extractCampaign(string $absolutePath): array
    {
        $text = $this->readText($absolutePath);

        $ai = $this->extractWithAi($absolutePath, $text, 'campaign');

        if ($ai === null) {
            throw new RuntimeException('AI_UNAVAILABLE');
        }

        return $this->result($this->campaignFields($ai), $text, 'gemini');
    }

    /**
     * Baca teks PDF; PDF rusak/terkunci dianggap tidak punya teks.
     */
    private function readText(string $absolutePath): string
    {
        try {
            $text = $this->regexExtractor->text($absolutePath);
        } catch (Throwable) {
            return '';
        }

        return mb_substr(trim($text), 0, self::MAX_TEXT);
    }

    /**
     * Minta Gemini mengekstrak field dari teks atau file PDF.
     *
     * @return array<string, mixed>|null
     */
    private function extractWithAi(string $absolutePath, string $text, string $kind): ?array
    {
        if (! $this->gemini->enabled()) {
            return null;
        }

        $system = 'Anda adalah asisten ekstraksi data dokumen sponsorship. '
            .'Hanya ambil informasi yang tertulis di dokumen. Jangan mengarang, '
            .'jangan menebak, dan balas dengan JSON saja.';

        $prompt = $kind === 'proposal'
            ? $this->proposalPrompt($text)
            : $this->campaignPrompt($text);

        if (mb_strlen($text) >= 20) {
            return $this->gemini->generateJson($prompt, $system);
        }

        // Tidak ada lapisan teks (kemungkinan scan) → kirim file ke Gemini.
        return $this->gemini->generateJsonFromPdf($absolutePath, $prompt, $system);
    }

    private function proposalPrompt(string $text): string
    {
        $schema = <<<'JSON'
{
  "title": "nama event/proposal atau null",
  "category": "nama kategori event atau null",
  "description": "deskripsi event atau null",
  "purpose": "tujuan penyelenggaraan event atau null",
  "target_audience": "target audiens atau null",
  "participant_estimate": 0,
  "location": "lokasi event atau null",
  "start_date": "YYYY-MM-DD atau null",
  "end_date": "YYYY-MM-DD atau null",
  "budget_min": 0,
  "budget_max": 0,
  "need_type": "Dana | Produk | Dana & produk | Jasa | null",
  "sponsorship_packages": "paket sponsorship atau null",
  "benefits": "benefit untuk sponsor atau null",
  "contact": "kontak penyelenggara atau null"
}
JSON;

        $document = $text !== ''
            ? "TEKS DOKUMEN:\n{$text}"
            : 'Baca dokumen PDF yang dilampirkan.';

        return <<<PROMPT
Ekstrak informasi proposal sponsorship dari dokumen berikut.

ATURAN WAJIB:
- Hanya isi field yang benar-benar tertulis di dokumen.
- Jika informasi tidak ada, gunakan null (atau 0 untuk angka yang tidak ada).
- Tanggal harus format YYYY-MM-DD. Jika hanya tahun/bulan tanpa hari, gunakan null.
- Angka anggaran berupa bilangan bulat rupiah tanpa titik/koma.
- Jangan menambahkan field lain di luar skema.

FORMAT BALASAN (JSON saja):
{$schema}

{$document}
PROMPT;
    }

    private function campaignPrompt(string $text): string
    {
        $schema = <<<'JSON'
{
  "title": "nama campaign atau null",
  "description": "deskripsi campaign atau null",
  "purpose": "tujuan campaign atau null",
  "category": "kategori/industri event yang dicari atau null",
  "target_audience": "target audiens atau null",
  "target_location": "target lokasi atau null",
  "budget_min": 0,
  "budget_max": 0,
  "event_types": "jenis event yang dicari atau null",
  "support_type": "bentuk dukungan yang diberikan atau null",
  "benefits": "benefit/bentuk kerja sama atau null",
  "period_start": "YYYY-MM-DD atau null",
  "period_end": "YYYY-MM-DD atau null",
  "estimated_participant": 0
}
JSON;

        $document = $text !== ''
            ? "TEKS DOKUMEN:\n{$text}"
            : 'Baca dokumen PDF yang dilampirkan.';

        return <<<PROMPT
Ekstrak informasi campaign sponsorship perusahaan dari dokumen berikut.

ATURAN WAJIB:
- Hanya isi field yang benar-benar tertulis di dokumen.
- Jika informasi tidak ada, gunakan null (atau 0 untuk angka yang tidak ada).
- Tanggal harus format YYYY-MM-DD. Jika hanya tahun/bulan tanpa hari, gunakan null.
- Angka anggaran berupa bilangan bulat rupiah tanpa titik/koma.
- Jangan menambahkan field lain di luar skema.

FORMAT BALASAN (JSON saja):
{$schema}

{$document}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function proposalFields(array $data): array
    {
        $fields = [];

        $this->putString($fields, 'title', $data['title'] ?? null, 255);
        $this->putString($fields, 'location', $data['location'] ?? null, 255);

        $category = $this->matchKategori($this->stringValue($data['category'] ?? null, 150));

        if ($category !== null) {
            $fields['idKategori'] = $category->idKategori;
            $fields['kategoriName'] = $category->name;
        }

        $description = $this->stringValue($data['description'] ?? null, 2000)
            ?? $this->stringValue($data['purpose'] ?? null, 2000);

        if ($description !== null) {
            $fields['desctiption'] = $description;
        }

        $audience = $this->stringValue($data['target_audience'] ?? null, 2000);

        if ($audience !== null) {
            $fields['target_audience'] = $audience;
        }

        $needType = $this->needType($data['need_type'] ?? null);

        if ($needType !== null) {
            $fields['needType'] = $needType;
        }

        $benefits = $this->stringValue($data['benefits'] ?? null, 2000)
            ?? $this->stringValue($data['sponsorship_packages'] ?? null, 2000);

        if ($benefits !== null) {
            $fields['benefits'] = $benefits;
        }

        [$min, $max] = $this->budget($data);

        if ($min !== null) {
            $fields['budgetMin'] = $min;
        }

        if ($max !== null) {
            $fields['budgetMax'] = $max;
        }

        [$start, $end] = $this->period(
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
        );

        if ($start !== null) {
            $fields['start_date'] = $start;
        }

        if ($end !== null) {
            $fields['end_date'] = $end;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function campaignFields(array $data): array
    {
        $fields = [];

        $this->putString($fields, 'title', $data['title'] ?? null, 150);

        $description = $this->stringValue($data['description'] ?? null, 3000);

        if ($description !== null) {
            $fields['description'] = $description;
        }

        $purpose = $this->stringValue($data['purpose'] ?? null, 1000);

        if ($purpose !== null) {
            $fields['purpose'] = $purpose;
        }

        $category = $this->matchKategori($this->stringValue($data['category'] ?? null, 150));

        if ($category !== null) {
            $fields['idKategori'] = $category->idKategori;
            $fields['kategoriName'] = $category->name;
        }

        $audience = $this->stringValue($data['target_audience'] ?? null, 500)
            ?? $this->stringValue($data['target_location'] ?? null, 500);

        if ($audience !== null) {
            $fields['target_audience'] = $audience;
        }

        $this->putString($fields, 'target_location', $data['target_location'] ?? null, 500);

        [$min, $max] = $this->budget($data);

        if ($min !== null) {
            $fields['budgetMin'] = $min;
        }

        if ($max !== null) {
            $fields['budgetMax'] = $max;
        }

        $this->putString($fields, 'supportType', $data['support_type'] ?? null, 150);

        $benefits = $this->stringValue($data['benefits'] ?? null, 2000)
            ?? $this->stringValue($data['event_types'] ?? null, 2000);

        if ($benefits !== null) {
            $fields['benefits'] = $benefits;
        }

        $participant = $this->intValue($data['estimated_participant'] ?? null);

        if ($participant !== null) {
            $fields['estimatedParticipant'] = $participant;
        }

        [$start, $end] = $this->period(
            $data['period_start'] ?? null,
            $data['period_end'] ?? null,
        );

        if ($start !== null) {
            $fields['periodStart'] = $start;
        }

        if ($end !== null) {
            $fields['periodEnd'] = $end;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{fields: array<string, mixed>, filled: array<int, string>, textLength: int, source: string}
     */
    private function result(array $fields, string $text, string $source): array
    {
        return [
            'fields' => $fields,
            'filled' => $this->labelsFor(array_keys($fields)),
            'textLength' => mb_strlen($text),
            'source' => $source,
        ];
    }

    /**
     * Cocokkan nama kategori hasil AI dengan tabel `kategori`.
     */
    private function matchKategori(?string $name): ?Kategori
    {
        if ($name === null) {
            return null;
        }

        $needle = Str::slug($name);

        if ($needle === '') {
            return null;
        }

        $categories = Kategori::query()->get(['idKategori', 'name']);

        return $categories->first(fn (Kategori $kategori) => Str::slug((string) $kategori->name) === $needle)
            ?? $categories->first(function (Kategori $kategori) use ($needle): bool {
                $haystack = Str::slug((string) $kategori->name);

                return $haystack !== ''
                    && (str_contains($needle, $haystack) || str_contains($haystack, $needle));
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int|null, 1: int|null}
     */
    private function budget(array $data): array
    {
        $min = $this->intValue($data['budget_min'] ?? null);
        $max = $this->intValue($data['budget_max'] ?? null);

        // Satu nilai saja (mis. "anggaran Rp50.000.000") dipakai untuk
        // kedua batas. Ini bukan menebak: nilainya memang dari dokumen.
        if ($min === null && $max !== null) {
            $min = $max;
        }

        if ($max === null && $min !== null) {
            $max = $min;
        }

        if ($min !== null && $max !== null && $max < $min) {
            [$min, $max] = [$max, $min];
        }

        return [$min, $max];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function period(mixed $start, mixed $end): array
    {
        $start = $this->dateValue($start);
        $end = $this->dateValue($end);

        if ($start !== null && $end !== null && $end < $start) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    private function needType(mixed $value): ?string
    {
        $value = $this->stringValue($value, 50);

        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower($value);

        return match (true) {
            str_contains($normalized, 'dana') && str_contains($normalized, 'produk') => 'Dana & produk',
            str_contains($normalized, 'dana') || str_contains($normalized, 'fund') => 'Dana',
            str_contains($normalized, 'produk') || str_contains($normalized, 'barang') => 'Produk',
            str_contains($normalized, 'jasa') => 'Jasa',
            default => in_array($value, self::NEED_TYPES, true) ? $value : null,
        };
    }

    private function stringValue(mixed $value, int $maxLength): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function intValue(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        $number = (int) $digits;

        return $number >= 0 && $number <= 999999999999 ? $number : null;
    }

    private function dateValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            $date = CarbonImmutable::createFromFormat($format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function putString(array &$fields, string $key, mixed $value, int $maxLength): void
    {
        $string = $this->stringValue($value, $maxLength);

        if ($string !== null) {
            $fields[$key] = $string;
        }
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function labelsFor(array $keys): array
    {
        $labels = [
            'title' => 'Judul',
            'idKategori' => 'Kategori',
            'kategoriName' => 'Kategori',
            'location' => 'Lokasi',
            'needType' => 'Jenis kebutuhan',
            'desctiption' => 'Deskripsi',
            'description' => 'Deskripsi',
            'purpose' => 'Tujuan',
            'target_audience' => 'Target audiens',
            'target_location' => 'Target lokasi',
            'benefits' => 'Benefit',
            'supportType' => 'Jenis dukungan',
            'budgetMin' => 'Anggaran',
            'budgetMax' => 'Anggaran',
            'start_date' => 'Tanggal',
            'end_date' => 'Tanggal',
            'periodStart' => 'Periode',
            'periodEnd' => 'Periode',
            'estimatedParticipant' => 'Perkiraan peserta',
        ];

        return collect($keys)
            ->map(fn (string $key) => $labels[$key] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
