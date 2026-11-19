<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ekstraksi PDF proposal & campaign.
 *
 * Gemini di-fake; yang dipastikan:
 *   - hanya PDF yang diterima (selain itu 422)
 *   - ukuran mengikuti config `sponsormatch.pdf.max_kb`
 *   - hasil ekstraksi mengisi field form tetapi TIDAK menyimpan apa pun
 *   - bila AI tidak tersedia / balasannya tidak valid, tidak ada data palsu
 *     yang disimpan
 */
class PdfExtractionTest extends TestCase
{
    use RefreshDatabase;

    private Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategori = Kategori::create([
            'name' => 'Teknologi',
            'deskripsiKategori' => 'Seminar dan festival teknologi.',
        ]);
    }

    public function test_proposal_pdf_is_extracted_with_ai_without_persisting(): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            '*' => Http::response($this->geminiResponse([
                'title' => 'Tech Summit 2026',
                'category' => 'Teknologi',
                'description' => 'Konferensi teknologi tahunan.',
                'purpose' => 'Menghubungkan talenta digital.',
                'target_audience' => 'Mahasiswa dan profesional muda.',
                'location' => 'Jakarta',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-02',
                'budget_min' => 50_000_000,
                'budget_max' => 100_000_000,
                'need_type' => 'Dana',
                'benefits' => 'Logo pada banner.',
            ])),
        ]);

        $eo = User::factory()->create();

        $response = $this->actingAs($eo)->post(
            route('proposals.ekstrak'),
            ['file' => $this->fakePdf()],
            ['Accept' => 'application/json'],
        );

        $response->assertOk()
            ->assertJsonPath('source', 'gemini')
            ->assertJsonPath('fields.title', 'Tech Summit 2026')
            ->assertJsonPath('fields.location', 'Jakarta')
            ->assertJsonPath('fields.idKategori', $this->kategori->idKategori)
            ->assertJsonPath('fields.budgetMin', 50_000_000)
            ->assertJsonPath('fields.budgetMax', 100_000_000)
            ->assertJsonPath('fields.needType', 'Dana');

        $this->assertContains('Judul', $response->json('filled'));

        // Hasil ekstraksi hanya untuk mengisi form; belum ada yang disimpan.
        $this->assertDatabaseCount('proposal', 0);
    }

    public function test_campaign_pdf_is_extracted_with_ai_without_persisting(): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            '*' => $this->campaignAiResponse([
                'title' => 'Campaign Ramah Lingkungan',
                'description' => 'Campaign keberlanjutan perusahaan.',
                'purpose' => 'Mendukung event hijau.',
                'category' => 'Teknologi',
                'target_audience' => 'Mahasiswa',
                'target_location' => 'Jakarta',
                'budget_min' => 25_000_000,
                'budget_max' => 75_000_000,
                'support_type' => 'Dana',
                'benefits' => 'Logo pada semua materi.',
                'period_start' => '2026-12-01',
                'period_end' => '2026-12-31',
                'estimated_participant' => 500,
            ]),
        ]);

        $company = User::factory()->company()->create();

        $response = $this->actingAs($company)->post(
            route('company.campaigns.ekstrak'),
            ['file' => $this->fakePdf('campaign.pdf')],
            ['Accept' => 'application/json'],
        );

        $response->assertOk()
            ->assertJsonPath('source', 'gemini')
            ->assertJsonPath('fields.title', 'Campaign Ramah Lingkungan')
            ->assertJsonPath('fields.idKategori', $this->kategori->idKategori)
            ->assertJsonPath('fields.supportType', 'Dana')
            ->assertJsonPath('fields.periodEnd', '2026-12-31')
            ->assertJsonPath('fields.estimatedParticipant', 500);

        $this->assertDatabaseCount('campaign', 0);
    }

    public function test_pdf_endpoint_rejects_non_pdf_file(): void
    {
        $eo = User::factory()->create();

        $this->actingAs($eo)->post(
            route('proposals.ekstrak'),
            ['file' => UploadedFile::fake()->create('catatan.txt', 10, 'text/plain')],
            ['Accept' => 'application/json'],
        )->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_pdf_endpoint_rejects_file_over_configured_limit(): void
    {
        config([
            'services.gemini.key' => 'test-key',
            'sponsormatch.pdf.max_kb' => 1,
        ]);

        $eo = User::factory()->create();

        $this->actingAs($eo)->post(
            route('proposals.ekstrak'),
            ['file' => UploadedFile::fake()->create('proposal.pdf', 4, 'application/pdf')],
            ['Accept' => 'application/json'],
        )->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_default_proposal_pdf_limit_is_30_mb(): void
    {
        $this->assertSame(30720, (int) config('sponsormatch.pdf.max_kb'));

        $eo = User::factory()->create();

        $response = $this->actingAs($eo)->post(
            route('proposals.ekstrak'),
            ['file' => $this->fakePdfWithSize('proposal.pdf', 30721)],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertStringContainsString(
            '30 MB',
            (string) $response->json('errors.file.0'),
        );
    }

    public function test_campaign_extraction_returns_503_when_ai_is_unavailable(): void
    {
        config(['services.gemini.key' => null]);

        $company = User::factory()->company()->create();

        $this->actingAs($company)->post(
            route('company.campaigns.ekstrak'),
            ['file' => $this->fakePdf('campaign.pdf')],
            ['Accept' => 'application/json'],
        )->assertStatus(503);

        $this->assertDatabaseCount('campaign', 0);
    }

    public function test_campaign_extraction_returns_503_when_ai_response_is_invalid(): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => 'ini bukan JSON']],
                    ],
                ]],
            ]),
        ]);

        $company = User::factory()->company()->create();

        $this->actingAs($company)->post(
            route('company.campaigns.ekstrak'),
            ['file' => $this->fakePdf('campaign.pdf')],
            ['Accept' => 'application/json'],
        )->assertStatus(503);

        // Tidak boleh ada campaign "dummy" yang dibuat dari respons rusak.
        $this->assertDatabaseCount('campaign', 0);
    }

    /**
     * File PDF uji dengan isi nyata.
     *
     * `UploadedFile::fake()->create()` hanya memalsukan UKURAN, isinya kosong,
     * sehingga Gemini tidak menerima byte apa pun. Helper ini menulis file
     * sementara sungguhan agar jalur inline PDF ikut teruji.
     */
    private function fakePdf(string $name = 'proposal.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');

        file_put_contents($path, "%PDF-1.4\n% SponsorMatch test PDF\n".str_repeat('0', 512));

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    /**
     * PDF uji berukuran tertentu tanpa mengisi seluruh isi ke memori.
     * Header PDF asli tetap ditulis agar lolos validasi mime.
     */
    private function fakePdfWithSize(string $name, int $kilobytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        $handle = fopen($path, 'w+');

        fwrite($handle, "%PDF-1.4\n% SponsorMatch test PDF\n");
        ftruncate($handle, $kilobytes * 1024);
        fclose($handle);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function geminiResponse(array $payload): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'parts' => [
                        ['text' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                    ],
                ],
            ]],
        ];
    }

    /**
     * Respons ekstraksi campaign diuji lewat jalur yang sama.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function campaignAiResponse(array $payload): array
    {
        return $this->geminiResponse($payload);
    }
}
