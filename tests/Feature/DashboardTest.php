<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_company_dashboard_receives_the_company_name()
    {
        $user = User::factory()->company()->create(['name' => 'Siti Aminah']);

        CompanyProfile::create([
            'idUser' => $user->id,
            'companyName' => 'PT Maju Jaya',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Index')
            ->where('auth.user.name', 'Siti Aminah')
            ->where('auth.user.company_name', 'PT Maju Jaya')
        );
    }
}
