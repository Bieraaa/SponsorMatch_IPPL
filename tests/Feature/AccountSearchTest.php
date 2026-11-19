<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\EoProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pencarian akun pada topbar dan profil publik hasil pencarian.
 */
class AccountSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_company_by_company_name_and_links_to_company_profile(): void
    {
        $actor = User::factory()->create();
        $company = User::factory()->company()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'rating' => 4.6,
        ]);

        CompanyProfile::create([
            'idUser' => $company->id,
            'companyName' => 'Nusantara Energi',
            'industry' => 'Energi Terbarukan',
        ]);

        $response = $this->actingAs($actor)->getJson(route('search', ['q' => 'Nusantara']));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('results.0.id', $company->id)
            ->assertJsonPath('results.0.name', 'Nusantara Energi')
            ->assertJsonPath('results.0.role', 'sponsor')
            ->assertJsonPath('results.0.role_label', 'Perusahaan')
            ->assertJsonPath('results.0.subtitle', 'Energi Terbarukan')
            ->assertJsonPath('results.0.rating', 4.6)
            ->assertJsonPath('results.0.url', route('companies.show', $company->id));
    }

    public function test_search_finds_company_by_industry(): void
    {
        $actor = User::factory()->create();
        $company = User::factory()->company()->create();

        CompanyProfile::create([
            'idUser' => $company->id,
            'companyName' => 'Sinar Media',
            'industry' => 'Media dan Periklanan',
        ]);

        $this->actingAs($actor)
            ->getJson(route('search', ['q' => 'Periklanan']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $company->id)
            ->assertJsonPath('results.0.industry', 'Media dan Periklanan');
    }

    public function test_search_finds_event_organizer_by_organization_name(): void
    {
        $actor = User::factory()->create();
        $eo = User::factory()->create(['name' => 'Rina']);

        EoProfile::create([
            'idUser' => $eo->id,
            'eo_name' => 'Kreativa Event',
            'eo_field' => 'Festival Musik',
        ]);

        $this->actingAs($actor)
            ->getJson(route('search', ['q' => 'Kreativa']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $eo->id)
            ->assertJsonPath('results.0.name', 'Kreativa Event')
            ->assertJsonPath('results.0.role', 'eo')
            ->assertJsonPath('results.0.subtitle', 'Festival Musik')
            ->assertJsonPath('results.0.url', route('accounts.show', $eo->id));
    }

    public function test_empty_query_returns_account_suggestions(): void
    {
        $actor = User::factory()->create();
        $company = User::factory()->company()->create();

        CompanyProfile::create([
            'idUser' => $company->id,
            'companyName' => 'Aksara Digital',
        ]);

        $this->actingAs($actor)
            ->getJson(route('search'))
            ->assertOk()
            ->assertJsonFragment(['id' => $company->id])
            ->assertJsonFragment(['id' => $actor->id]);
    }

    public function test_search_excludes_inactive_accounts(): void
    {
        $actor = User::factory()->create();
        $inactive = User::factory()->company()->create(['isActive' => false]);

        CompanyProfile::create([
            'idUser' => $inactive->id,
            'companyName' => 'Tidak Aktif Jaya',
        ]);

        $this->actingAs($actor)
            ->getJson(route('search', ['q' => 'Tidak Aktif']))
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_event_organizer_account_page_is_available(): void
    {
        $viewer = User::factory()->create();
        $eo = User::factory()->create([
            'name' => 'Rina Pratama',
            'rating' => 4.2,
        ]);

        EoProfile::create([
            'idUser' => $eo->id,
            'eo_name' => 'Kreativa Event',
            'eo_field' => 'Festival Musik',
        ]);

        $this->actingAs($viewer)
            ->get(route('accounts.show', $eo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounts/Show')
                ->where('account.id', $eo->id)
                ->where('account.name', 'Kreativa Event')
                ->where('account.field', 'Festival Musik')
                ->where('account.rating', 4.2));
    }

    public function test_company_account_page_redirects_to_company_profile(): void
    {
        $viewer = User::factory()->create();
        $company = User::factory()->company()->create();

        CompanyProfile::create([
            'idUser' => $company->id,
            'companyName' => 'Nusantara Energi',
        ]);

        $this->actingAs($viewer)
            ->get(route('accounts.show', $company))
            ->assertRedirect(route('companies.show', $company));
    }

    public function test_guests_cannot_use_account_search(): void
    {
        $this->getJson(route('search', ['q' => 'Nusantara']))
            ->assertUnauthorized();
    }
}
