<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Offer;
use App\Models\Proposal;
use App\Models\ProposalViewLog;
use App\Models\TrackingPipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alur proposal end-to-end:
 *   draft → terbitkan → siap dikirim → kirim ke sponsor → inbox sponsor
 *   → buka (viewed) → ajukan penawaran (negotiating) → terima/tolak.
 */
class ProposalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategori = Kategori::create([
            'name' => 'Musik & Konser',
            'deskripsiKategori' => 'Konser dan festival musik.',
        ]);
    }

    public function test_publish_requires_an_uploaded_document(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $proposal = $this->proposal($owner, ['status' => 'draft', 'file_path' => '']);

        $this->actingAs($owner)
            ->post(route('proposals.publish', $proposal))
            ->assertRedirect();

        $this->assertSame('draft', $proposal->fresh()->status);
    }

    public function test_owner_can_publish_a_draft_with_a_document(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $proposal = $this->proposal($owner, ['status' => 'draft', 'file_path' => 'proposals/demo.pdf']);

        Storage::disk('local')->put('proposals/demo.pdf', '%PDF-1.4 dokumen');

        $this->actingAs($owner)
            ->post(route('proposals.publish', $proposal))
            ->assertRedirect();

        $this->assertSame('published', $proposal->fresh()->status);

        // Status turunan untuk EO: published tanpa tracking = "ready".
        $props = $this->actingAs($owner)
            ->get(route('proposals.index'))
            ->viewData('page')['props'];

        $this->assertSame('ready', $props['proposals']['data'][0]['status']);
        $this->assertSame(1, $props['statusCounts']['ready']);
    }

    public function test_other_users_cannot_publish_someone_elses_proposal(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $proposal = $this->proposal($owner, ['status' => 'draft', 'file_path' => 'proposals/demo.pdf']);

        Storage::disk('local')->put('proposals/demo.pdf', '%PDF-1.4 dokumen');

        $this->actingAs($intruder)
            ->post(route('proposals.publish', $proposal))
            ->assertForbidden();

        $this->assertSame('draft', $proposal->fresh()->status);
    }

    public function test_send_page_lists_sponsors_and_marks_already_sent(): void
    {
        $owner = User::factory()->create();
        $sentTo = User::factory()->company()->create(['name' => 'Bank Nusantara']);
        $newSponsor = User::factory()->company()->create(['name' => 'Tekno Elektrik']);

        $proposal = $this->proposal($owner, ['status' => 'published']);
        $this->tracking($proposal, $sentTo, 'sent');

        $props = $this->actingAs($owner)
            ->get(route('proposals.send', $proposal))
            ->assertOk()
            ->viewData('page')['props'];

        $sponsors = collect($props['sponsors'])->keyBy('id');

        $this->assertTrue($sponsors[$sentTo->id]['alreadySent']);
        $this->assertFalse($sponsors[$newSponsor->id]['alreadySent']);
        $this->assertSame('1', $props['proposal']['nomor']);
    }

    public function test_send_creates_trackings_and_auto_publishes_a_draft(): void
    {
        $owner = User::factory()->create();
        $first = User::factory()->company()->create();
        $second = User::factory()->company()->create();

        $proposal = $this->proposal($owner, ['status' => 'draft']);

        $this->actingAs($owner)
            ->post(route('proposals.send.store', $proposal), [
                'sponsor_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('proposals.index', ['selected' => $proposal->idProposal]));

        $this->assertSame('published', $proposal->fresh()->status);
        $this->assertSame(2, TrackingPipeline::count());
        $this->assertSame(2, TrackingPipeline::where('stage', 'sent')->count());

        // Kirim ulang tidak menggandakan tracking.
        $this->actingAs($owner)
            ->post(route('proposals.send.store', $proposal), [
                'sponsor_ids' => [$first->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, TrackingPipeline::count());
    }

    public function test_send_ignores_accounts_that_are_not_sponsors(): void
    {
        $owner = User::factory()->create();
        $otherEo = User::factory()->create();
        $sponsor = User::factory()->company()->create();

        $proposal = $this->proposal($owner, ['status' => 'published']);

        $this->actingAs($owner)
            ->post(route('proposals.send.store', $proposal), [
                'sponsor_ids' => [$otherEo->id, $sponsor->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, TrackingPipeline::count());
        $this->assertSame($sponsor->id, TrackingPipeline::first()->company_user_id);
    }

    public function test_other_users_cannot_open_the_send_page(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $proposal = $this->proposal($owner, ['status' => 'published']);

        $this->actingAs($intruder)
            ->get(route('proposals.send', $proposal))
            ->assertForbidden();
    }

    public function test_sponsor_inbox_is_only_for_company_accounts(): void
    {
        $eo = User::factory()->create();
        $sponsor = User::factory()->company()->create();

        $this->actingAs($eo)->get(route('sponsor.proposals.index'))->assertForbidden();
        $this->actingAs($sponsor)->get(route('sponsor.proposals.index'))->assertOk();
    }

    public function test_sponsor_inbox_only_contains_proposals_sent_to_them(): void
    {
        $owner = User::factory()->create();
        $sponsor = User::factory()->company()->create();
        $otherSponsor = User::factory()->company()->create();

        $mine = $this->proposal($owner, ['title' => 'Untuk Sponsor Ini']);
        $this->tracking($mine, $sponsor, 'sent');

        $other = $this->proposal($owner, ['title' => 'Untuk Sponsor Lain']);
        $this->tracking($other, $otherSponsor, 'sent');

        $props = $this->actingAs($sponsor)
            ->get(route('sponsor.proposals.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(1, $props['items']);
        $this->assertSame('Untuk Sponsor Ini', $props['items'][0]['title']);
        $this->assertSame(1, $props['counts']['new']);
    }

    public function test_opening_a_proposal_logs_the_view_and_marks_it_viewed(): void
    {
        $owner = User::factory()->create();
        $sponsor = User::factory()->company()->create();

        $proposal = $this->proposal($owner);
        $tracking = $this->tracking($proposal, $sponsor, 'sent');

        $this->actingAs($sponsor)
            ->get(route('sponsor.proposals.show', $tracking))
            ->assertOk();

        $this->assertSame('viewed', $tracking->fresh()->stage);
        $this->assertSame(1, ProposalViewLog::count());
        $this->assertSame($sponsor->id, ProposalViewLog::first()->idViewer);

        // Buka lagi tidak menambah log baru.
        $this->actingAs($sponsor)->get(route('sponsor.proposals.show', $tracking))->assertOk();
        $this->assertSame(1, ProposalViewLog::count());
    }

    public function test_sponsor_cannot_open_a_proposal_sent_to_another_company(): void
    {
        $owner = User::factory()->create();
        $sponsor = User::factory()->company()->create();
        $intruder = User::factory()->company()->create();

        $tracking = $this->tracking($this->proposal($owner), $sponsor, 'sent');

        $this->actingAs($intruder)
            ->get(route('sponsor.proposals.show', $tracking))
            ->assertForbidden();
    }

    public function test_sponsor_offer_moves_the_tracking_to_negotiating(): void
    {
        $owner = User::factory()->create();
        $sponsor = User::factory()->company()->create();

        $tracking = $this->tracking($this->proposal($owner), $sponsor, 'viewed');

        $this->actingAs($sponsor)
            ->post(route('sponsor.proposals.offer', $tracking), [
                'support_type' => 'Dana',
                'amount' => 25000000,
                'message' => 'Kami siap mendukung panggung utama.',
            ])
            ->assertRedirect();

        $this->assertSame('negotiating', $tracking->fresh()->stage);

        $offer = Offer::first();
        $this->assertNotNull($offer);
        $this->assertSame($sponsor->id, $offer->from_user_id);
        $this->assertSame('pending', $offer->status);
        $this->assertSame(25000000, $offer->amount);
        $this->assertSame('Kami siap mendukung panggung utama.', $offer->description);
    }

    public function test_sponsor_cannot_send_an_offer_for_another_companys_tracking(): void
    {
        $owner = User::factory()->create();
        $sponsor = User::factory()->company()->create();
        $intruder = User::factory()->company()->create();

        $tracking = $this->tracking($this->proposal($owner), $sponsor, 'viewed');

        $this->actingAs($intruder)
            ->post(route('sponsor.proposals.offer', $tracking), [
                'support_type' => 'Dana',
                'amount' => 1000,
            ])
            ->assertForbidden();

        $this->assertSame(0, Offer::count());
    }

    public function test_sponsor_accepting_the_proposal_sets_deal_and_accepts_open_offers(): void
    {
        $owner = User::factory()->create();
        $sponsor = User::factory()->company()->create();

        $tracking = $this->tracking($this->proposal($owner), $sponsor, 'negotiating');
        $offer = $this->offer($tracking, $sponsor, 'pending');

        $this->actingAs($sponsor)
            ->post(route('sponsor.proposals.respond', $tracking), ['action' => 'accept'])
            ->assertRedirect();

        $this->assertSame('deal', $tracking->fresh()->stage);
        $this->assertSame('accepted', $offer->fresh()->status);

        // Di sisi EO, proposal otomatis berstatus "Disetujui".
        $props = $this->actingAs($owner)
            ->get(route('proposals.index'))
            ->viewData('page')['props'];

        $this->assertSame('approved', $props['proposals']['data'][0]['status']);
    }

    public function test_sponsor_rejecting_the_proposal_sets_rejected_and_rejects_open_offers(): void
    {
        $owner = User::factory()->create();
        $sponsor = User::factory()->company()->create();

        $tracking = $this->tracking($this->proposal($owner), $sponsor, 'viewed');
        $offer = $this->offer($tracking, $sponsor, 'pending');

        $this->actingAs($sponsor)
            ->post(route('sponsor.proposals.respond', $tracking), ['action' => 'reject'])
            ->assertRedirect();

        $this->assertSame('rejected', $tracking->fresh()->stage);
        $this->assertSame('rejected', $offer->fresh()->status);
    }

    public function test_a_finished_tracking_cannot_be_responded_to_again(): void
    {
        $owner = User::factory()->create();
        $sponsor = User::factory()->company()->create();

        $tracking = $this->tracking($this->proposal($owner), $sponsor, 'deal');

        $this->actingAs($sponsor)
            ->post(route('sponsor.proposals.respond', $tracking), ['action' => 'reject'])
            ->assertRedirect();

        $this->assertSame('deal', $tracking->fresh()->stage);
    }

    public function test_sponsor_can_download_the_document_they_received(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $sponsor = User::factory()->company()->create();

        $proposal = $this->proposal($owner, ['file_path' => 'proposals/demo.pdf']);
        $this->tracking($proposal, $sponsor, 'sent');
        Storage::disk('local')->put('proposals/demo.pdf', '%PDF-1.4 dokumen');

        $this->actingAs($sponsor)
            ->get(route('sponsor.proposals.dokumen', $proposal))
            ->assertOk()
            ->assertDownload('demo.pdf');
    }

    public function test_send_supports_more_than_two_companies_in_one_action(): void
    {
        $owner = User::factory()->create();
        $companies = collect(range(1, 3))
            ->map(fn () => User::factory()->company()->create());

        $proposal = $this->proposal($owner, ['status' => 'published']);

        $this->actingAs($owner)
            ->post(route('proposals.send.store', $proposal), [
                'sponsor_ids' => $companies->pluck('id')->all(),
                'message' => 'Kami membuka kesempatan kolaborasi untuk tiga perusahaan.',
            ])
            ->assertRedirect();

        $this->assertSame(3, TrackingPipeline::count());
        $this->assertSame(3, TrackingPipeline::where('stage', 'sent')->count());
        $this->assertSame(
            3,
            TrackingPipeline::where('message', 'Kami membuka kesempatan kolaborasi untuk tiga perusahaan.')->count(),
        );
        $this->assertEqualsCanonicalizing(
            $companies->pluck('id')->all(),
            TrackingPipeline::pluck('company_user_id')->all(),
        );
    }

    public function test_send_rejects_duplicate_sponsor_ids(): void
    {
        $owner = User::factory()->create();
        $company = User::factory()->company()->create();
        $proposal = $this->proposal($owner, ['status' => 'published']);

        $this->actingAs($owner)
            ->post(route('proposals.send.store', $proposal), [
                'sponsor_ids' => [$company->id, $company->id],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, TrackingPipeline::count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function proposal(User $owner, array $attributes = []): Proposal
    {
        return Proposal::create([
            'idUser' => $owner->id,
            'idKategori' => $this->kategori->idKategori,
            'title' => 'Proposal Uji',
            'desctiption' => 'Deskripsi proposal uji.',
            'target_audience' => 'Mahasiswa.',
            'location' => 'Bandung',
            'needType' => 'Dana',
            'budgetMin' => 10000000,
            'budgetMax' => 50000000,
            'benefits' => 'Logo utama, booth',
            'file_path' => '',
            'status' => 'published',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-03',
            ...$attributes,
        ]);
    }

    private function tracking(Proposal $proposal, User $sponsor, string $stage): TrackingPipeline
    {
        return TrackingPipeline::create([
            'idProposal' => $proposal->idProposal,
            'company_user_id' => $sponsor->id,
            'stage' => $stage,
            'initiatedBy' => 'Event_Organizer',
        ]);
    }

    private function offer(TrackingPipeline $tracking, User $sponsor, string $status): Offer
    {
        return Offer::create([
            'idTracking' => $tracking->idTracking,
            'from_user_id' => $sponsor->id,
            'support_type' => 'Dana',
            'amount' => 25000000,
            'status' => $status,
        ]);
    }
}
