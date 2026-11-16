<?php

namespace App\Services;

use App\Models\Kategori;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Ekstraksi isi dokumen proposal PDF menjadi nilai form.
 *
 * TUJUAN
 * ------
 * User cukup mengunggah PDF proposal; field form (judul, kategori, lokasi,
 * anggaran, tanggal, target audiens, deskripsi, benefit) diisi otomatis
 * sehingga tidak perlu mengetik ulang.
 *
 * CARA KERJA
 * ----------
 * 1. Teks PDF diambil dengan `smalot/pdfparser` (PDF berbasis teks).
 * 2. Tiap field dicari lewat pola label yang lazim dipakai proposal
 *    sponsorship Indonesia/Inggris, mis. "Judul : ...", "Anggaran : Rp...".
 * 3. Hasilnya hanya berisi field yang BENAR-BENAR ditemukan. Field yang
 *    tidak ditemukan dibiarkan kosong supaya user mengisinya sendiri —
 *    kita tidak pernah menebak data yang tidak ada di dokumen.
 *
 * KETERBATASAN (jujur, bukan bug)
 * -------------------------------
 * - PDF hasil scan (gambar) tidak punya lapisan teks, jadi tidak bisa
 *   dibaca tanpa OCR. Controller akan mengembalikan pesan yang jelas.
 * - Nama label yang tidak standar bisa terlewat; user tetap bisa
 *   mengisi manual. Ekstraksi bersifat "membantu", bukan menggantikan.
 */
class PdfProposalExtractor
{
    /** Batas panjang teks yang dipakai untuk pencarian. */
    private const MAX_TEXT = 60000;

    /** Nama bulan Indonesia + Inggris → angka. */
    private const MONTHS = [
        'januari' => 1, 'jan' => 1,
        'februari' => 2, 'feb' => 2, 'pebruari' => 2,
        'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5, 'may' => 5,
        'juni' => 6, 'jun' => 6,
        'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'agt' => 8, 'aug' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'desember' => 12, 'des' => 12, 'dec' => 12,
    ];

    /**
     * Baca PDF dan kembalikan nilai form yang berhasil dikenali.
     *
     * @return array{fields: array<string, mixed>, filled: array<int, string>, textLength: int}
     *
     * @throws Throwable kalau file bukan PDF yang bisa dibaca
     */
    public function extract(string $absolutePath): array
    {
        $text = $this->readText($absolutePath);

        if (mb_strlen(trim($text)) < 20) {
            throw new \RuntimeException('EMPTY_TEXT');
        }

        $text = mb_substr($text, 0, self::MAX_TEXT);

        $fields = [];

        $title = $this->firstLabelValue($text, ['judul proposal', 'judul', 'title', 'nama kegiatan', 'nama event']);

        if ($title !== null) {
            $fields['title'] = $title;
        }

        $kategori = $this->matchKategori($text);

        if ($kategori !== null) {
            $fields['idKategori'] = $kategori->idKategori;
            $fields['kategoriName'] = $kategori->name;
        }

        $location = $this->firstLabelValue($text, ['lokasi', 'tempat', 'venue', 'location']);

        if ($location !== null) {
            $fields['location'] = $location;
        }

        $needType = $this->detectNeedType($text);

        if ($needType !== null) {
            $fields['needType'] = $needType;
        }

        $audience = $this->firstLabelValue($text, ['target audiens', 'target audience', 'target peserta', 'audiens']);

        if ($audience !== null) {
            $fields['target_audience'] = $audience;
        }

        $description = $this->section($text, ['deskripsi proposal', 'deskripsi', 'description', 'ringkasan', 'tentang acara']);

        if ($description !== null) {
            $fields['desctiption'] = $description;
        }

        $benefits = $this->benefits($text);

        if ($benefits !== null) {
            $fields['benefits'] = $benefits;
        }

        [$budgetMin, $budgetMax] = $this->budget($text);

        if ($budgetMin !== null) {
            $fields['budgetMin'] = $budgetMin;
        }

        if ($budgetMax !== null) {
            $fields['budgetMax'] = $budgetMax;
        }

        [$start, $end] = $this->period($text);

        if ($start !== null) {
            $fields['start_date'] = $start;
        }

        if ($end !== null) {
            $fields['end_date'] = $end;
        }

        return [
            'fields' => $fields,
            'filled' => $this->labelsFor(array_keys($fields)),
            'textLength' => mb_strlen($text),
        ];
    }

    /**
     * Teks mentah PDF (dipakai juga oleh PdfExtractionService untuk Gemini).
     *
     * @throws Throwable kalau PDF tidak bisa dibaca
     */
    public function text(string $absolutePath): string
    {
        return $this->readText($absolutePath);
    }

    /**
     * Ambil teks dari PDF.
     */
    private function readText(string $absolutePath): string
    {
        $parser = new Parser;
        $document = $parser->parseFile($absolutePath);

        // Rapikan spasi ganda & banyak baris kosong supaya regex label
        // tidak "pecah" karena layout PDF yang berantakan.
        $text = $document->getText();
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
    }

    /**
     * Cari nilai satu baris berlabel, mis. "Judul : Festival Musik 2026".
     *
     * @param  array<int, string>  $labels
     */
    private function firstLabelValue(string $text, array $labels): ?string
    {
        $pattern = '/(?:^|\n)\s*(?:'.implode('|', array_map(
            fn (string $label) => preg_quote($label, '/'),
            $labels,
        )).')\s*[:\-–]\s*([^\n]{2,160})/iu';

        if (! preg_match($pattern, $text, $matches)) {
            return null;
        }

        $value = $this->clean($matches[1]);

        return $value === '' ? null : $value;
    }

    /**
     * Ambil satu paragraf/daftar setelah label (dipakai untuk deskripsi).
     *
     * @param  array<int, string>  $labels
     */
    private function section(string $text, array $labels, int $maxLength = 700): ?string
    {
        $pattern = '/(?:^|\n)\s*(?:'.implode('|', array_map(
            fn (string $label) => preg_quote($label, '/'),
            $labels,
        )).')\s*[:\-–]\s*([\s\S]*?)(?=\n\s*\n|\n\s*[A-Za-z][A-Za-z ]{2,30}\s*[:\-–]|$)/iu';

        if (! preg_match($pattern, $text, $matches)) {
            return null;
        }

        $value = $this->clean($matches[1]);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    /**
     * Daftar benefit/hak sponsor.
     */
    private function benefits(string $text): ?string
    {
        $raw = $this->section($text, ['benefit sponsor', 'benefit', 'benefits', 'hak sponsor', 'paket sponsor', 'fasilitas sponsor'], 1200);

        if ($raw === null) {
            return null;
        }

        $items = preg_split('/\n|•|·|\u{2022}|;/u', $raw) ?: [];

        $items = collect($items)
            ->map(fn (string $item) => $this->clean(preg_replace('/^\s*(?:[-*–]|\d+[.)])\s*/u', '', $item) ?? ''))
            ->filter(fn (string $item) => $item !== '')
            ->values();

        return $items->isEmpty() ? null : $items->implode(', ');
    }

    /**
     * Rentang anggaran (minimal & maksimal).
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function budget(string $text): array
    {
        // 1. Baris berlabel: "Anggaran : Rp50.000.000 - Rp150.000.000".
        $line = $this->firstLabelValue($text, ['anggaran', 'budget', 'dana', 'nilai sponsorship', 'kebutuhan dana']);

        $source = $line ?? $text;

        if (preg_match_all('/(?:rp\.?\s*)?(\d[\d.,]{3,})/iu', $source, $matches)) {
            $numbers = collect($matches[1])
                ->map(fn (string $number) => $this->toNumber($number))
                ->filter(fn (int $number) => $number >= 1000)
                ->values();

            if ($numbers->isNotEmpty()) {
                $min = (int) $numbers->first();
                $max = (int) ($numbers->get(1) ?? $min);

                return [$min, max($min, $max)];
            }
        }

        return [null, null];
    }

    /**
     * Tanggal mulai & selesai.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function period(string $text): array
    {
        // Prioritas: baris berlabel "Periode/Tanggal Pelaksanaan".
        $line = $this->firstLabelValue($text, ['periode', 'tanggal pelaksanaan', 'tanggal kegiatan', 'waktu pelaksanaan', 'tanggal', 'period']);

        $dates = $this->datesIn($line ?? '');
        $fromLabel = $dates !== [];

        if (! $fromLabel) {
            $dates = $this->datesIn($text);
        }

        if ($dates === []) {
            return [null, null];
        }

        $start = $dates[0];

        // Kalau hanya ada satu tanggal, anggap acara sehari (mulai = selesai).
        $end = $dates[1] ?? ($fromLabel ? $start : null);

        return [$start, $end];
    }

    /**
     * Cari semua tanggal pada sebuah teks, urut sesuai posisi kemunculan.
     *
     * @return array<int, string> format Y-m-d
     */
    private function datesIn(string $text): array
    {
        $found = [];

        // 15 Nov 2026 / 15 November 2026
        if (preg_match_all('/\b(\d{1,2})\s+([A-Za-z]{3,9})\.?\s+(\d{4})\b/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $month = self::MONTHS[mb_strtolower($match[2][0])] ?? null;

                if ($month === null) {
                    continue;
                }

                $date = $this->buildDate((int) $match[3][0], $month, (int) $match[1][0]);

                if ($date !== null) {
                    $found[$match[0][1]] = $date;
                }
            }
        }

        // 2026-11-15
        if (preg_match_all('/\b(\d{4})-(\d{2})-(\d{2})\b/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $date = $this->buildDate((int) $match[1][0], (int) $match[2][0], (int) $match[3][0]);

                if ($date !== null) {
                    $found[$match[0][1]] = $date;
                }
            }
        }

        // 15/11/2026 (hari/bulan/tahun)
        if (preg_match_all('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $date = $this->buildDate((int) $match[3][0], (int) $match[2][0], (int) $match[1][0]);

                if ($date !== null) {
                    $found[$match[0][1]] = $date;
                }
            }
        }

        ksort($found);

        return array_values(array_unique($found));
    }

    /**
     * Validasi & format tanggal ke Y-m-d.
     */
    private function buildDate(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->toDateString();
    }

    /**
     * Cocokkan teks PDF dengan daftar kategori di database.
     */
    private function matchKategori(string $text): ?Kategori
    {
        // 1. Baris berlabel "Kategori : Musik & Konser".
        $label = $this->firstLabelValue($text, ['kategori event', 'kategori', 'category']);

        if ($label !== null) {
            $exact = Kategori::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower($label)])
                ->first();

            if ($exact !== null) {
                return $exact;
            }

            $partial = Kategori::all()->first(
                fn (Kategori $item) => Str::contains(mb_strtolower($label), mb_strtolower($item->name)),
            );

            if ($partial !== null) {
                return $partial;
            }
        }

        // 2. Nama kategori muncul di badan dokumen.
        $lower = mb_strtolower($text);

        return Kategori::all()->first(
            fn (Kategori $item) => Str::contains($lower, mb_strtolower($item->name)),
        );
    }

    /**
     * Tentukan jenis kebutuhan dari kata kunci.
     */
    private function detectNeedType(string $text): ?string
    {
        $lower = mb_strtolower($text);
        $dana = Str::contains($lower, ['dana', 'fund', 'anggaran', 'budget']);
        $produk = Str::contains($lower, ['produk', 'product', 'barang', 'goods']);
        $jasa = Str::contains($lower, ['jasa', 'service']);

        return match (true) {
            $dana && $produk => 'Dana & produk',
            $dana => 'Dana',
            $produk => 'Produk',
            $jasa => 'Jasa',
            default => null,
        };
    }

    /**
     * "50.000.000" / "50,000,000" → 50000000.
     */
    private function toNumber(string $value): int
    {
        $digits = preg_replace('/\D/', '', $value) ?? '0';

        return (int) $digits;
    }

    /**
     * Rapikan nilai hasil regex.
     */
    private function clean(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B-–:");
    }

    /**
     * Nama field internal → label yang enak dibaca user.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function labelsFor(array $keys): array
    {
        $labels = [
            'title' => 'Judul',
            'idKategori' => 'Kategori',
            'location' => 'Lokasi',
            'needType' => 'Jenis kebutuhan',
            'desctiption' => 'Deskripsi',
            'target_audience' => 'Target audiens',
            'benefits' => 'Benefit',
            'budgetMin' => 'Anggaran',
            'budgetMax' => 'Anggaran',
            'start_date' => 'Tanggal',
            'end_date' => 'Tanggal',
        ];

        return collect($keys)
            ->map(fn (string $key) => $labels[$key] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
