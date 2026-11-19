<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Offer;
use App\Models\Proposal;
use App\Models\TrackingPipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Negosiasi penawaran dua arah:
 *   kirim penawaran → penerima menerima / menolak / mengirim penawaran balik.
 *
 * Aturan utama yang diuji:
 *   - hanya peserta tracking (EO pemilik proposal / perusahaan penerima)
 *   - hanya PENERIMA yang boleh merespons
 *   - hanya penawaran `pending` yang bisa direspons
 *   - menerima = deal + semua penawaran lain pada tracking ditutup
 *   - menolak tidak mematikan jalur negosiasi
 *   - counter membuat baris anak (parent_offer_id) dan menandai induk `counter`
 */
class OfferNegotiationTest extends TestCase
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

    public function test_offer_page_only_lists_offers_for_the_signed_in_participant(): void
    {
        $firstEo = User::factory()->create();
        $firstCompany = User::factory()->company()->create();

        $secondEo = User::factory()->create();
        $secondCompany = User::factory()->company()->create();

        $firstOffer = $this->offer(
            $this->tracking($this->proposal($firstEo), $firstCompany, 'negotiating'),
            $firstCompany,
        );

        $secondOffer = $this->offer(
            $this->tracking($this->proposal($secondEo), $secondCompany, 'negotiating'),
            $secondCompany,
        );

        $eoProps = $this->actingAs($firstEo)
            ->get(route('offers.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(1, $eoProps['items']);
        $this->assertSame($firstOffer->idOffer, $eoProps['items'][0]['id']);

        $companyProps = $this->actingAs($secondCompany)
            ->get(route('offers.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(1, $companyProps['items']);
        $this->assertSame($secondOffer->idOffer, $companyProps['items'][0]['id']);
    }

    public function test_recipient_can_accept_an_offer_and_other_pending_offers_are_closed(): void
    {
        $eo = User::factory()->create();
        $company = User::factory()->company()->create();
        $tracking = $this->tracking($this->proposal($eo), $company, 'negotiating');

        $accepted = $this->offer($tracking, $company);
        $other = $this->offer($tracking, $company);

        $this->actingAs($eo)
            ->post(route('offers.respond', $accepted), ['action' => 'accept'])
            ->assertRedirect();

        $this->assertSame('accepted', $accepted->fresh()->status);
        $this->assertSame('rejected', $other->fresh()->status);
        $this->assertSame('deal', $tracking->fresh()->stage);
    }

    public function test_sender_cannot_respond_to_their_own_offer(): void
    {
        $eo = User::factory()->create();
        $company = User::factory()->company()->create();
        $tracking = $this->tracking($this->proposal($eo), $company, 'negotiating');

        $offer = $this->offer($tracking, $company);

        $this->actingAs($company)
            ->post(route('offers.respond', $offer), ['action' => 'accept'])
            ->assertForbidden();

        $this->assertSame('pending', $offer->fresh()->status);
    }

    public function test_users_outside_the_negotiation_cannot_respond(): void
    {
        $eo = User::factory()->create();
        $company = User::factory()->company()->create();
        $intruder = User::factory()->create();

        $tracking = $this->tracking($this->proposal($eo), $company, 'negotiating');
        $offer = $this->offer($tracking, $company);

        $this->actingAs($intruder)
            ->post(route('offers.respond', $offer), ['action' => 'accept'])
            ->assertForbidden();

        $this->assertSame('pending', $offer->fresh()->status);
    }

    public function test_recipient_can_reject_with_a_reason_and_tracking_stays_open(): void
    {
        $eo = User::factory()->create();
        $company = User::factory()->company()->create();
        $tracking = $this->tracking($this->proposal($eo), $company, 'negotiating');

        $offer = $this->offer($tracking, $company);

        $this->actingAs($eo)
            ->post(route('offers.respond', $offer), [
                'action' => 'reject',
                'reject_reason' => 'Nominal masih di bawah kebutuhan minimum.',
            ])
            ->assertRedirect();

        $this->assertSame('rejected', $offer->fresh()->status);
        $this->assertSame(
            'Nominal masih di bawah kebutuhan minimum.',
            $offer->fresh()->rejectReason,
        );
        $this->assertSame('viewed', $tracking->fresh()->stage);
    }

    public function test_recipient_can_send_a_counter_offer_as_a_child_row(): void
    {
        $eo = User::factory()->create();
        $company = User::factory()->company()->create();
        $tracking = $this->tracking($this->proposal($eo), $company, 'negotiating');

        $original = $this->offer($tracking, $company);

        $this->actingAs($eo)
            ->post(route('offers.respond', $original), [
                'action' => 'counter',
                'support_type' => 'Dana & produk',
                'amount' => '35.000.000',
                'message' => 'Kami dapat menyesuaikan dengan tambahan produk.',
                'benefits' => 'Logo utama dan booth.',
            ])
            ->assertRedirect();

        $this->assertSame('counter', $original->fresh()->status);
        $this->assertSame('negotiating', $tracking->fresh()->stage);

        $counter = Offer::query()
            ->where('parent_offer_id', $original->idOffer)
            ->first();

        $this->assertNotNull($counter);
        $this->assertSame($eo->id, $counter->from_user_id);
        $this->assertSame('pending', $counter->status);
        $this->assertSame('Dana & produk', $counter->support_type);
        $this->assertSame(35_000_000, $counter->amount);
        $this->assertSame('Kami dapat menyesuaikan dengan tambahan produk.', $counter->description);
        $this->assertSame('Logo utama dan booth.', $counter->benefits);

        // Balasan counter diterima oleh perusahaan dan menutup negosiasi.
        $this->actingAs($company)
            ->post(route('offers.respond', $counter), ['action' => 'accept'])
            ->assertRedirect();

        $this->assertSame('accepted', $counter->fresh()->status);
        $this->assertSame('deal', $tracking->fresh()->stage);
    }

    public function test_only_pending_offers_can_be_responded_to(): void
    {
        $eo = User::factory()->create();
        $company = User::factory()->company()->create();
        $tracking = $this->tracking($this->proposal($eo), $company, 'negotiating');

        $offer = $this->offer($tracking, $company, 'accepted');

        $this->actingAs($eo)
            ->post(route('offers.respond', $offer), ['action' => 'reject'])
            ->assertRedirect();

        $this->assertSame('accepted', $offer->fresh()->status);
        $this->assertSame('negotiating', $tracking->fresh()->stage);
    }

    public function test_finished_tracking_cannot_be_responded_to(): void
    {
        $eo = User::factory()->create();
        $company = User::factory()->company()->create();
        $tracking = $this->tracking($this->proposal($eo), $company, 'deal');

        $offer = $this->offer($tracking, $company);

        $this->actingAs($eo)
            ->post(route('offers.respond', $offer), ['action' => 'accept'])
            ->assertRedirect();

        $this->assertSame('pending', $offer->fresh()->status);
        $this->assertSame('deal', $tracking->fresh()->stage);
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

    private function offer(TrackingPipeline $tracking, User $sender, string $status = 'pending'): Offer
    {
        return Offer::create([
            'idTracking' => $tracking->idTracking,
            'from_user_id' => $sender->id,
            'support_type' => 'Dana',
            'amount' => 20_000_000,
            'description' => 'Dukungan awal untuk acara.',
            'status' => $status,
        ]);
    }
}
