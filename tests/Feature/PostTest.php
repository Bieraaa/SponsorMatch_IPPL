<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('posts.index'))->assertRedirect(route('login'));
    }

    public function test_feed_lists_only_visible_posts_from_active_authors(): void
    {
        $author = User::factory()->create(['name' => 'Rani Event']);
        $inactive = User::factory()->create(['isActive' => false]);

        $visible = $this->makePost($author, 'Postingan tampil');
        $this->makePost($author, 'Postingan dihapus', deleteAt: now());
        $this->makePost($inactive, 'Postingan akun nonaktif');

        $response = $this->actingAs($author)->get(route('posts.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('posts/Index')
            ->where('scope', 'all')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $visible->idPost)
            ->where('posts.data.0.description', 'Postingan tampil')
            ->where('posts.data.0.author_id', $author->id)
            ->where('posts.data.0.author_name', 'Rani Event')
            ->where('posts.data.0.author_role', 'eo')
            ->where('posts.data.0.author_role_label', 'Event Organizer')
            ->where('totalPostCount', 1)
        );
    }

    public function test_mine_scope_only_lists_posts_owned_by_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = $this->makePost($user, 'Punyaku');
        $this->makePost($other, 'Punya orang lain');

        $response = $this->actingAs($user)
            ->get(route('posts.index', ['scope' => 'mine']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'mine')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $mine->idPost)
            ->where('myPostCount', 1)
            ->where('totalPostCount', 2)
        );
    }

    public function test_company_posts_use_the_company_name_and_logo(): void
    {
        $company = User::factory()->company()->create(['name' => 'Budi Santoso']);

        CompanyProfile::create([
            'idUser' => $company->id,
            'companyName' => 'PT Maju Jaya',
        ]);

        $this->makePost($company, 'Kabar perusahaan');

        $response = $this->actingAs($company)->get(route('posts.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.author_name', 'PT Maju Jaya')
            ->where('posts.data.0.company_name', 'PT Maju Jaya')
            ->where('posts.data.0.author_role', 'sponsor')
            ->where('posts.data.0.author_role_label', 'Perusahaan')
        );
    }

    public function test_user_can_create_a_post_with_an_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'description' => 'Kegiatan bakti sosial minggu ini.',
            'image' => UploadedFile::fake()->image('kegiatan.jpg', 1200, 800),
        ]);

        $response->assertRedirect(route('posts.index', ['scope' => 'mine']));

        $post = Post::query()->firstOrFail();

        $this->assertSame($user->id, (int) $post->idUser);
        $this->assertSame('Kegiatan bakti sosial minggu ini.', $post->description);
        $this->assertNotNull($post->imagePath);
        Storage::disk('public')->assertExists($post->imagePath);
    }

    public function test_post_requires_text_or_an_image(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('posts.index'))
            ->post(route('posts.store'), [
                'description' => '   ',
            ]);

        $response->assertRedirect(route('posts.index'));
        $response->assertSessionHasErrors('description');
        $this->assertDatabaseCount('post', 0);
    }

    public function test_image_must_have_an_allowed_format_and_size(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('posts.index'))
            ->post(route('posts.store'), [
                'description' => 'Dokumen',
                'image' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('image');

        $this->actingAs($user)
            ->from(route('posts.index'))
            ->post(route('posts.store'), [
                'description' => 'Gambar terlalu besar',
                'image' => UploadedFile::fake()->image('besar.jpg')->size(5000),
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('post', 0);
    }

    public function test_only_the_owner_can_update_a_post(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $post = $this->makePost($owner, 'Teks awal');

        $this->actingAs($other)
            ->patch(route('posts.update', $post), ['description' => 'Dibajak'])
            ->assertForbidden();

        $this->assertSame('Teks awal', $post->fresh()->description);

        $this->actingAs($owner)
            ->patch(route('posts.update', $post), ['description' => 'Teks baru'])
            ->assertRedirect(route('posts.index', ['scope' => 'mine']));

        $this->assertSame('Teks baru', $post->fresh()->description);
    }

    public function test_owner_can_remove_the_image_when_updating(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $path = UploadedFile::fake()
            ->image('lama.jpg')
            ->store('posts', 'public');

        $post = $this->makePost($user, 'Dengan gambar', imagePath: $path);

        $this->actingAs($user)
            ->patch(route('posts.update', $post), [
                'description' => 'Tanpa gambar',
                'remove_image' => true,
            ])
            ->assertRedirect(route('posts.index', ['scope' => 'mine']));

        $post->refresh();

        $this->assertNull($post->imagePath);
        $this->assertSame('Tanpa gambar', $post->description);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_only_the_owner_can_delete_a_post_and_deletion_is_soft(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $other = User::factory()->create();

        $path = UploadedFile::fake()
            ->image('hapus.jpg')
            ->store('posts', 'public');

        $post = $this->makePost($owner, 'Akan dihapus', imagePath: $path);

        $this->actingAs($other)
            ->delete(route('posts.destroy', $post))
            ->assertForbidden();

        $this->assertNull($post->fresh()->deleteAt);

        $this->actingAs($owner)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index', ['scope' => 'mine']));

        $this->assertNotNull($post->fresh()->deleteAt);
        $this->assertDatabaseHas('post', ['idPost' => $post->idPost]);
        Storage::disk('public')->assertMissing($path);

        $this->actingAs($owner)
            ->get(route('posts.index'))
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 0));
    }

    public function test_deleted_posts_cannot_be_updated_or_deleted_again(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user, 'Sudah dihapus', deleteAt: now());

        $this->actingAs($user)
            ->patch(route('posts.update', $post), ['description' => 'Ubah'])
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('posts.destroy', $post))
            ->assertNotFound();
    }

    public function test_eo_dashboard_receives_latest_posts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->makePost($user, 'Postingan lama');
        $latest = $this->makePost($user, 'Postingan terbaru');
        $this->makePost($other, 'Postingan akun lain');
        $this->makePost($user, 'Postingan dihapus', deleteAt: now());

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Index')
            ->has('dashboardEo.latestPosts', 2)
            ->where('dashboardEo.latestPosts.0.id', $latest->idPost)
            ->where('dashboardEo.latestPosts.0.description', 'Postingan terbaru')
        );
    }

    public function test_company_dashboard_receives_latest_posts(): void
    {
        $company = User::factory()->company()->create();

        $post = $this->makePost($company, 'Kabar terbaru perusahaan');

        $response = $this->actingAs($company)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Index')
            ->has('companyDashboard.latestPosts', 1)
            ->where('companyDashboard.latestPosts.0.id', $post->idPost)
        );
    }

    private function makePost(
        User $user,
        string $description,
        ?string $imagePath = null,
        mixed $deleteAt = null,
    ): Post {
        $post = new Post;
        $post->idUser = $user->id;
        $post->description = $description;
        $post->imagePath = $imagePath;
        $post->deleteAt = $deleteAt;
        $post->save();

        return $post;
    }
}
