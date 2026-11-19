<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\EventReport;
use App\Models\Kategori;
use App\Models\Offer;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\TrackingPipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Riwayat kolaborasi: daftar kerja sama, laporan realisasi event, dan ulasan.
 *
 * Aturan yang diuji:
 *   - hanya peserta tracking yang melihat / mengubah data
 *   - laporan hanya untuk EO pemilik proposal dan hanya saat kerja sama berjalan
 *   - laporan memindahkan tracking ke `done`
 *   - ulasan hanya setelah `done`, satu kali per pihak, dan memperbarui rating
 */
class CollaborationTest extends TestCase
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

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('collaborations.index'))
            ->assertRedirect(route('login'));
    }

    public function test_history_only_lists_tracking_owned_by_the_signed_in_user(): void
    {
        $eo = User::factory()->create();
        $company = $this->company();

        $otherEo = User::factory()->create();
        $otherCompany = $this->company();

        $owned = $this->tracking($this->proposal($eo), $company, 'deal');
        $this->tracking($this->proposal($otherEo), $otherCompany, 'deal');

        $eoProps = $this->actingAs($eo)
            ->get(route('collaborations.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(1, $eoProps['items']);
        $this->assertSame($owned->idTracking, $eoProps['items'][0]['id']);

        $companyProps = $this->actingAs($otherCompany)
            ->get(route('collaborations.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(1, $companyProps['items']);
        $this->assertSame($otherEo->id, $companyProps['items'][0]['counterpart_id']);
    }

    public function test_history_excludes_negotiations_that_are_not_deals(): void
    {
        $eo = User::factory()->create();
        $proposal = $this->proposal($eo);

        $this->tracking($proposal, $this->company(), 'sent');
        $this->tracking($proposal, $this->company(), 'negotiating');
        $deal = $this->tracking($proposal, $this->company(), 'deal');

        $props = $this->actingAs($eo)
            ->get(route('collaborations.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(1, $props['items']);
        $this->assertSame($deal->idTracking, $props['items'][0]['id']);
    }

    public function test_eo_can_submit_an_event_report_and_tracking_becomes_done(): void
    {
        $eo = User::factory()->create();
        $company = $this->company();
        $tracking = $this->tracking($this->proposal($eo), $company, 'deal');

        $this->actingAs($eo)
            ->post(route('collaborations.report', $tracking), [
                'description' => 'Event berjalan lancar dengan 500 peserta.',
                'goal_achieved' => true,
            ])
            ->assertRedirect();

        $report = EventReport::query()->where('idTracking', $tracking->idTracking)->first();

        $this->assertNotNull($report);
        $this->assertSame('Event berjalan lancar dengan 500 peserta.', $report->decription);
        $this->assertTrue($report->goal_achieved);
        $this->assertSame('done', $tracking->fresh()->stage);
    }

    public function test_only_the_eo_owner_can_submit_a_report(): void
    {
        $eo = User::factory()->create();
        $company = $this->company();
        $tracking = $this->tracking($this->proposal($eo), $company, 'deal');

        $this->actingAs($company)
            ->post(route('collaborations.report', $tracking), [
                'description' => 'Mencoba mengirim laporan.',
                'goal_achieved' => true,
            ])
            ->assertForbidden();

        $this->assertNull(
            EventReport::query()->where('idTracking', $tracking->idTracking)->first(),
        );
        $this->assertSame('deal', $tracking->fresh()->stage);
    }

    public function test_report_is_rejected_when_collaboration_is_already_done(): void
    {
        $eo = User::factory()->create();
        $company = $this->company();
        $tracking = $this->tracking($this->proposal($eo), $company, 'done');

        $this->actingAs($eo)
            ->post(route('collaborations.report', $tracking), [
                'description' => 'Laporan tambahan.',
                'goal_achieved' => false,
            ])
            ->assertStatus(422);
    }

    public function test_participants_can_review_after_completion_and_rating_is_updated(): void
    {
        $eo = User::factory()->create();
        $company = $this->company();
        $tracking = $this->tracking($this->proposal($eo), $company, 'done');

        $this->actingAs($eo)
            ->post(route('collaborations.review', $tracking), [
                'rating' => 5,
                'comment' => 'Komunikasi cepat dan pembayaran tepat waktu.',
            ])
            ->assertRedirect();

        $review = Review::query()->where('idTracking', $tracking->idTracking)->first();

        $this->assertNotNull($review);
        $this->assertSame($eo->id, $review->from_user_id);
        $this->assertSame($company->id, $review->to_user_id);
        $this->assertSame(5, $review->rating);
        $this->assertSame('5.0', $company->fresh()->rating);
    }

    public function test_a_participant_can_only_review_once(): void
    {
        $eo = User::factory()->create();
        $company = $this->company();
        $tracking = $this->tracking($this->proposal($eo), $company, 'done');

        $this->actingAs($eo)->post(route('collaborations.review', $tracking), [
            'rating' => 4,
        ])->assertRedirect();

        $this->actingAs($eo)->post(route('collaborations.review', $tracking), [
            'rating' => 2,
        ])->assertRedirect();

        $this->assertSame(
            1,
            Review::query()
                ->where('idTracking', $tracking->idTracking)
                ->where('from_user_id', $eo->id)
                ->count(),
        );
    }

    public function test_review_is_rejected_before_the_collaboration_is_done(): void
    {
        $eo = User::factory()->create();
        $company = $this->company();
        $tracking = $this->tracking($this->proposal($eo), $company, 'deal');

        $this->actingAs($eo)
            ->post(route('collaborations.review', $tracking), ['rating' => 5])
            ->assertStatus(422);

        $this->assertSame(0, Review::query()->count());
    }

    public function test_users_outside_the_collaboration_cannot_review(): void
    {
        $eo = User::factory()->create();
        $company = $this->company();
        $intruder = User::factory()->create();
        $tracking = $this->tracking($this->proposal($eo), $company, 'done');

        $this->actingAs($intruder)
            ->post(route('collaborations.review', $tracking), ['rating' => 5])
            ->assertForbidden();

        $this->assertSame(0, Review::query()->count());
    }

    public function test_history_payload_includes_the_accepted_offer_amount(): void
    {
        $eo = User::factory()->create();
        $company = $this->company();
        $tracking = $this->tracking($this->proposal($eo), $company, 'deal');

        Offer::create([
            'idTracking' => $tracking->idTracking,
            'from_user_id' => $company->id,
            'support_type' => 'Dana',
            'amount' => 35_000_000,
            'status' => 'accepted',
        ]);

        $props = $this->actingAs($company)
            ->get(route('collaborations.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(35_000_000, $props['items'][0]['amount']);
        $this->assertSame('Rp 35.000.000', $props['items'][0]['amount_label']);
        $this->assertSame('Rp 35.000.000', $props['stats']['total_value_label']);
    }

    public function test_billing_page_is_a_static_preview_without_transactions(): void
    {
        $user = User::factory()->create(['status_subscription' => 'Reguler']);

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/Index')
                ->where('current_plan', 'free')
                ->where('payment_ready', false)
                ->where('invoices', [])
            );
    }

    public function test_help_page_exposes_role_specific_quick_links(): void
    {
        $company = $this->company();

        $this->actingAs($company)
            ->get(route('help.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('help/Index')
                ->where('role', 'company')
                ->has('quickLinks', 6)
                ->has('faqs', 4)
            );
    }

    private function company(): User
    {
        $user = User::factory()->company()->create();

        CompanyProfile::create([
            'idUser' => $user->id,
            'companyName' => 'PT Maju Jaya',
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function proposal(User $owner, array $attributes = []): Proposal
    {
        return Proposal::create([
            'idUser' => $owner->id,
            'idKategori' => $this->kategori->idKategori,
            'title' => 'Festival Teknologi Nusantara',
            'desctiption' => 'Festival teknologi tahunan.',
            'target_audience' => 'Mahasiswa dan profesional muda.',
            'location' => 'Samarinda',
            'needType' => 'Dana',
            'budgetMin' => 25_000_000,
            'budgetMax' => 75_000_000,
            'benefits' => 'Logo pada banner dan panggung.',
            'file_path' => 'proposals/test.pdf',
            'status' => 'published',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDay()->toDateString(),
            ...$attributes,
        ]);
    }

    private function tracking(Proposal $proposal, User $company, string $stage): TrackingPipeline
    {
        return TrackingPipeline::create([
            'idProposal' => $proposal->idProposal,
            'company_user_id' => $company->id,
            'stage' => $stage,
            'initiatedBy' => 'Event_Organizer',
        ]);
    }
}
