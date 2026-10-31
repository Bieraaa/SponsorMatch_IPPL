<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'nomor_telepon' => '+62 812-3456-7890',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('+62 812-3456-7890', $user->nomor_telepon);
    }

    public function test_empty_phone_number_is_allowed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'nomor_telepon' => '',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull($user->refresh()->nomor_telepon);
    }

    public function test_invalid_phone_number_is_rejected()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'nomor_telepon' => 'bukan-nomor-telepon',
            ]);

        $response
            ->assertSessionHasErrors('nomor_telepon')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_email_cannot_be_changed_through_the_profile_endpoint()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $originalEmail = $user->email;

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Nama Baru',
                'email' => 'penyerang@example.com',
            ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame($originalEmail, $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_sensitive_account_fields_cannot_be_changed()
    {
        $user = User::factory()->create([
            'role' => UserRole::EventOrganizer,
            'status_subscription' => 'Reguler',
            'isActive' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'role' => 'sponsor',
                'status_subscription' => 'Subscription',
                'isActive' => false,
                'rating' => 5,
                'id' => 9999,
            ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(UserRole::EventOrganizer, $user->role);
        $this->assertSame('Reguler', $user->status_subscription);
        $this->assertTrue($user->isActive);
        $this->assertNull($user->rating);
    }

    public function test_user_cannot_update_another_users_profile()
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['nomor_telepon' => '081200000000']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Pengguna',
            'nomor_telepon' => '081299999999',
            'id' => $other->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nama Pengguna', $user->refresh()->name);
        $this->assertSame('081200000000', $other->refresh()->nomor_telepon);
    }

    public function test_user_can_upload_a_jpg_profile_photo()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => UploadedFile::fake()->image('foto.jpg', 400, 400),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $path = $user->refresh()->getRawOriginal('avatar');

        $this->assertNotNull($path);
        $this->assertStringStartsWith('avatars/'.$user->id.'/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_user_can_upload_a_png_profile_photo()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('foto.png', 200, 200),
        ])->assertSessionHasNoErrors();

        $path = $user->refresh()->getRawOriginal('avatar');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_gif_profile_photo_is_rejected()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => UploadedFile::fake()->image('foto.gif', 100, 100),
            ])
            ->assertSessionHasErrors('avatar')
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->getRawOriginal('avatar'));
    }

    public function test_svg_profile_photo_is_rejected()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $svg = UploadedFile::fake()->createWithContent(
            'foto.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>'
        );

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => $svg,
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->getRawOriginal('avatar'));
    }

    public function test_pdf_profile_photo_is_rejected()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->getRawOriginal('avatar'));
    }

    public function test_file_with_spoofed_extension_is_rejected()
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $fakeImage = UploadedFile::fake()->createWithContent(
            'foto.jpg',
            '<?php echo "bukan gambar"; ?>'
        );

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => $fakeImage,
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->getRawOriginal('avatar'));
    }

    public function test_profile_photo_larger_than_the_limit_is_rejected()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $maxKb = (int) config('sponsormatch.profile_photo.max_kb');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => UploadedFile::fake()
                    ->image('besar.jpg', 100, 100)
                    ->size($maxKb + 1),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->getRawOriginal('avatar'));
    }

    public function test_previous_profile_photo_is_removed_after_a_successful_replacement()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $oldPath = 'avatars/'.$user->id.'/lama.jpg';

        Storage::disk('public')->put($oldPath, 'old-file');
        $user->forceFill(['avatar' => $oldPath])->save();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('baru.png', 120, 120),
        ])->assertSessionHasNoErrors();

        $newPath = $user->refresh()->getRawOriginal('avatar');

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_avatar_path_is_not_changed_when_the_uploaded_file_is_invalid()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $oldPath = 'avatars/'.$user->id.'/tetap.png';

        Storage::disk('public')->put($oldPath, 'old-file');
        $user->forceFill(['avatar' => $oldPath])->save();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'avatar' => UploadedFile::fake()->image('invalid.gif', 50, 50),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertSame($oldPath, $user->refresh()->getRawOriginal('avatar'));
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
