<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CompanyProfile;
use App\Models\Kategori;
use App\Models\Proposal;
use App\Models\SponsorMatch;
use App\Models\TrackingPipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Rekomendasi dua arah:
 *   - EO  → perusahaan (`eo_to_company`)
 *   - perusahaan → proposal EO (`company_to_eo`)
 *
 * Gemini selalu di-fake sehingga test tidak pernah memanggil jaringan.
 * Yang dipastikan:
 *   - pre-filter kategori/anggaran berjalan sebelum AI
 *   - perusahaan tanpa campaign tetap masuk rekomendasi
 *   - campaign aktif dipakai, campaign kedaluwarsa tidak
 *   - hasil diurutkan berdasarkan skor dan dipaginasi
 *   - API key tidak bocor ke URL/respons
 *   - respons AI yang mengarang id diabaikan
 *   - aksi "Ajukan Penawaran" memakai trackingpipeline yang sudah ada
 */
class RecommendationMatchingTest extends TestCase
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

    public function test_eo_recommendation_includes_company_without_campaign(): void
    {
        // Tanpa API key → skor lokal, tidak ada request keluar.
        config(['services.gemini.key' => null]);

        $eo = User::factory()->create();
        $proposal = $this->makeProposal($eo);

        $company = User::factory()->company()->create();
        $this->makeProfile($company);

        $props = $this->actingAs($eo)
            ->get(route('recommendations.index', ['proposal' => $proposal->idProposal]))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(1, $props['recommendations']['total']);
        $this->assertSame('profile', $props['recommendations']['data'][0]['source']);
        $this->assertNull($props['recommendations']['data'][0]['campaign']);

        $this->assertDatabaseHas('matches', [
            'idProposal' => $proposal->idProposal,
            'company_user_id' => $company->id,
            'direction' => SponsorMatch::DIRECTION_EO_TO_COMPANY,
            'source' => 'profile',
        ]);
    }

    public function test_eo_recommendation_uses_active_campaign_and_ignores_expired_campaign(): void
    {
        config(['services.gemini.key' => null]);

        $eo = User::factory()->create();
        $proposal = $this->makeProposal($eo);

        $activeCompany = User::factory()->company()->create();
        $this->makeProfile($activeCompany, [
            'sponsorship_min' => null,
            'sponsorship_max' => null,
            'categories' => [],
        ]);
        $campaign = $this->makeCampaign($activeCompany);

        // Campaign sudah lewat periodEnd → bukan peluang aktif. Profil juga
        // tidak punya kategori/anggaran yang cocok, jadi harus disaring.
        $expiredCompany = User::factory()->company()->create();
        $this->makeProfile($expiredCompany, [
            'sponsorship_min' => 1_000,
            'sponsorship_max' => 2_000,
            'categories' => [],
        ]);
        $this->makeCampaign($expiredCompany, [
            'periodEnd' => now()->subDay()->toDateString(),
            'budgetMin' => 1_000,
            'budgetMax' => 2_000,
        ]);

        $props = $this->actingAs($eo)
            ->get(route('recommendations.index', ['proposal' => $proposal->idProposal]))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(1, $props['recommendations']['total']);

        $item = $props['recommendations']['data'][0];
        $this->assertSame($activeCompany->id, $item['company_id']);
        $this->assertSame('campaign', $item['source']);
        $this->assertSame($campaign->idCampaign, $item['campaign']['id']);

        $this->assertDatabaseMissing('matches', [
            'idProposal' => $proposal->idProposal,
            'company_user_id' => $expiredCompany->id,
        ]);
    }

    public function test_eo_recommendation_is_sorted_by_score_and_paginated(): void
    {
        config(['services.gemini.key' => 'test-key']);

        $eo = User::factory()->create();
        $proposal = $this->makeProposal($eo);
        $companies = [];

        for ($i = 0; $i < 8; $i++) {
            $company = User::factory()->company()->create();
            $this->makeProfile($company, ['companyName' => 'PT Sponsor '.$i]);
            $companies[$company->id] = 95 - ($i * 10);
        }

        Http::fake([
            '*' => Http::response($this->geminiResponse([
                'matches' => collect($companies)->map(
                    fn (int $score, int $id) => [
                        'company_id' => $id,
                        'campaign_id' => null,
                        'score' => $score,
                        'reason' => "Skor uji {$score}.",
                        'factors' => ['Faktor uji.'],
                    ],
                )->values()->all(),
            ])),
        ]);

        $first = $this->actingAs($eo)
            ->get(route('recommendations.index', ['proposal' => $proposal->idProposal]))
            ->assertOk()
            ->viewData('page')['props']['recommendations'];

        $this->assertSame(8, $first['total']);
        $this->assertSame(2, $first['last_page']);
        $this->assertCount(6, $first['data']);
        $this->assertSame(95, $first['data'][0]['score']);
        $this->assertSame(45, $first['data'][5]['score']);

        $second = $this->actingAs($eo)
            ->get(route('recommendations.index', [
                'proposal' => $proposal->idProposal,
                'page' => 2,
            ]))
            ->assertOk()
            ->viewData('page')['props']['recommendations'];

        $this->assertCount(2, $second['data']);
        $this->assertSame(35, $second['data'][0]['score']);
        $this->assertSame(25, $second['data'][1]['score']);
    }

    public function test_gemini_api_key_is_sent_as_header_and_never_exposed(): void
    {
        config(['services.gemini.key' => 'super-secret-test-key']);

        $eo = User::factory()->create();
        $proposal = $this->makeProposal($eo);
        $company = User::factory()->company()->create();
        $this->makeProfile($company);

        Http::fake([
            '*' => Http::response($this->geminiResponse([
                'matches' => [[
                    'company_id' => $company->id,
                    'campaign_id' => null,
                    'score' => 88,
                    'reason' => 'Cocok untuk diuji.',
                    'factors' => ['Kategori sama.'],
                ]],
            ])),
        ]);

        $response = $this->actingAs($eo)
            ->get(route('recommendations.index', ['proposal' => $proposal->idProposal]))
            ->assertOk();

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-goog-api-key', 'super-secret-test-key')
                && ! str_contains($request->url(), 'super-secret-test-key');
        });

        $response->assertDontSee('super-secret-test-key');
    }

    public function test_ai_results_with_unknown_ids_are_ignored(): void
    {
        config(['services.gemini.key' => 'test-key']);

        $eo = User::factory()->create();
        $proposal = $this->makeProposal($eo);
        $company = User::factory()->company()->create();
        $this->makeProfile($company);

        Http::fake([
            '*' => Http::response($this->geminiResponse([
                'matches' => [
                    [
                        'company_id' => 999_999,
                        'score' => 100,
                        'reason' => 'Perusahaan yang tidak ada.',
                        'factors' => [],
                    ],
                    [
                        'company_id' => $company->id,
                        'score' => 250,
                        'reason' => '',
                        'factors' => [],
                    ],
                ],
            ])),
        ]);

        $props = $this->actingAs($eo)
            ->get(route('recommendations.index', ['proposal' => $proposal->idProposal]))
            ->assertOk()
            ->viewData('page')['props'];

        // Entri tanpa alasan dianggap tidak valid → fallback skor lokal.
        $this->assertSame(1, $props['recommendations']['total']);
        $this->assertSame($company->id, $props['recommendations']['data'][0]['company_id']);
        $this->assertLessThanOrEqual(100, $props['recommendations']['data'][0]['score']);

        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseMissing('matches', ['company_user_id' => 999_999]);
    }

    public function test_company_recommendation_excludes_tracked_and_draft_proposals(): void
    {
        config(['services.gemini.key' => null]);

        $company = User::factory()->company()->create();
        $this->makeProfile($company);

        $eo = User::factory()->create();
        $recommended = $this->makeProposal($eo, ['title' => 'Proposal Direkomendasikan']);
        $tracked = $this->makeProposal($eo, ['title' => 'Proposal Sudah Dikirim']);
        $this->makeProposal($eo, ['title' => 'Proposal Draft', 'status' => 'draft']);

        TrackingPipeline::create([
            'idProposal' => $tracked->idProposal,
            'company_user_id' => $company->id,
            'idCampaign' => null,
            'stage' => 'sent',
            'initiatedBy' => 'Event_Organizer',
        ]);

        $props = $this->actingAs($company)
            ->get(route('company.recommendations.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(1, $props['recommendations']['total']);
        $this->assertSame($recommended->idProposal, $props['recommendations']['data'][0]['proposal_id']);

        $this->assertDatabaseHas('matches', [
            'idProposal' => $recommended->idProposal,
            'company_user_id' => $company->id,
            'direction' => SponsorMatch::DIRECTION_COMPANY_TO_EO,
        ]);
        $this->assertDatabaseMissing('matches', [
            'idProposal' => $tracked->idProposal,
            'company_user_id' => $company->id,
        ]);
    }

    public function test_company_recommendation_returns_empty_when_no_filter_is_available(): void
    {
        config(['services.gemini.key' => null]);

        $company = User::factory()->company()->create();
        CompanyProfile::create([
            'idUser' => $company->id,
            'companyName' => 'PT Tanpa Preferensi',
            'industry' => 'Teknologi',
        ]);

        $eo = User::factory()->create();
        $this->makeProposal($eo);

        $props = $this->actingAs($company)
            ->get(route('company.recommendations.index'))
            ->assertOk()
            ->viewData('page')['props'];

        // Tidak ada kategori/anggaran/campaign → jangan kirim seluruh
        // proposal ke AI. Halaman menampilkan kondisi kosong.
        $this->assertSame(0, $props['recommendations']['total']);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_company_interest_creates_tracking_without_duplicating_offer_logic(): void
    {
        config(['services.gemini.key' => null]);

        $company = User::factory()->company()->create();
        $this->makeProfile($company);

        $eo = User::factory()->create();
        $proposal = $this->makeProposal($eo);

        $this->actingAs($company)
            ->get(route('company.recommendations.index'))
            ->assertOk();

        $response = $this->actingAs($company)
            ->post(route('company.recommendations.interest', $proposal));

        $tracking = TrackingPipeline::query()
            ->where('idProposal', $proposal->idProposal)
            ->where('company_user_id', $company->id)
            ->first();

        $this->assertNotNull($tracking);
        $this->assertSame('Perusahaan', $tracking->initiatedBy);
        $this->assertSame('sent', $tracking->stage);
        $response->assertRedirect(route('sponsor.proposals.show', $tracking));

        // Menekan tombol dua kali tidak membuat baris tracking ganda.
        $this->actingAs($company)
            ->post(route('company.recommendations.interest', $proposal))
            ->assertRedirect();

        $this->assertSame(1, TrackingPipeline::query()
            ->where('idProposal', $proposal->idProposal)
            ->where('company_user_id', $company->id)
            ->count());
    }

    public function test_company_interest_rejects_proposals_outside_recommendations(): void
    {
        config(['services.gemini.key' => null]);

        $company = User::factory()->company()->create();
        $this->makeProfile($company);

        $eo = User::factory()->create();
        $proposal = $this->makeProposal($eo);

        // Belum pernah dihitung sebagai rekomendasi.
        $this->actingAs($company)
            ->post(route('company.recommendations.interest', $proposal))
            ->assertForbidden();

        $this->assertDatabaseCount('trackingpipeline', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProposal(User $eo, array $overrides = []): Proposal
    {
        return Proposal::create(array_merge([
            'idUser' => $eo->id,
            'idKategori' => $this->kategori->idKategori,
            'title' => 'Festival Musik Nusantara',
            'desctiption' => 'Konser musik tahunan dengan pembicara nasional.',
            'target_audience' => 'Mahasiswa dan masyarakat umum.',
            'location' => 'Samarinda',
            'needType' => 'Dana',
            'budgetMin' => 50_000_000,
            'budgetMax' => 100_000_000,
            'benefits' => 'Logo pada banner dan panggung.',
            'file_path' => 'proposals/test.pdf',
            'status' => 'published',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDay()->toDateString(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProfile(User $company, array $overrides = []): CompanyProfile
    {
        $categories = $overrides['categories'] ?? [$this->kategori->idKategori];
        unset($overrides['categories']);

        $profile = CompanyProfile::create(array_merge([
            'idUser' => $company->id,
            'companyName' => 'PT Maju Jaya',
            'industry' => 'Teknologi',
            'description' => 'Perusahaan teknologi yang mendukung acara edukatif.',
            'campaign_Target' => 'Mahasiswa dan profesional muda.',
            'sponsorship_min' => 40_000_000,
            'sponsorship_max' => 120_000_000,
            'event_types' => 'Seminar, festival',
            'target_audience' => 'Mahasiswa',
        ], $overrides));

        $profile->categories()->sync($categories);

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCampaign(User $company, array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'idUser' => $company->id,
            'idKategori' => $this->kategori->idKategori,
            'title' => 'Campaign Teknologi 2026',
            'description' => 'Campaign untuk mendukung event teknologi.',
            'target_audience' => 'Mahasiswa',
            'budgetMin' => 40_000_000,
            'budgetMax' => 120_000_000,
            'supportType' => 'Dana',
            'estimatedParticipant' => 1000,
            'periodStart' => now()->subDay()->toDateString(),
            'periodEnd' => now()->addMonth()->toDateString(),
            'file_path' => 'campaigns/test.pdf',
            'status' => 'active',
        ], $overrides));
    }

    /**
     * Bungkus payload JSON ke bentuk respons generateContent Gemini.
     *
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
}
