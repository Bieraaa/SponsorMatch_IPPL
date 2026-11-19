<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Services\CompanyPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Feed dan pengelolaan postingan komunitas.
 *
 * Postingan tersedia untuk kedua peran (Event Organizer dan Perusahaan).
 * Semua pengguna login dapat membaca feed, tetapi hanya pemilik postingan
 * yang boleh mengubah atau menghapusnya.
 *
 * Tabel `post` tidak memakai SoftDeletes; penghapusan mengisi `deleteAt`
 * sehingga postingan hilang dari feed tanpa menghapus baris historis.
 */
class PostController extends Controller
{
    private const PER_PAGE = 8;

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($this->canUsePosts($user), 403, 'Halaman ini khusus akun Event Organizer dan Perusahaan.');

        $scope = $request->query('scope') === 'mine' ? 'mine' : 'all';

        $query = Post::query()
            ->visible()
            ->whereHas('user', fn ($author) => $author->where('isActive', true))
            ->with(['user.companyProfile']);

        if ($scope === 'mine') {
            $query->ownedBy($user);
        }

        /** @var LengthAwarePaginator<int, Post> $posts */
        $posts = $query
            ->orderByDesc('createdAt')
            ->orderByDesc('idPost')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $posts->through(fn (Post $post) => CompanyPresenter::post($post));

        $image = config('sponsormatch.post_image');

        return Inertia::render('posts/Index', [
            'posts' => $posts,
            'scope' => $scope,
            'myPostCount' => Post::query()->visible()->ownedBy($user)->count(),
            'totalPostCount' => Post::query()
                ->visible()
                ->whereHas('user', fn ($author) => $author->where('isActive', true))
                ->count(),
            'imageConfig' => [
                'max_kb' => (int) $image['max_kb'],
                'extensions' => $image['extensions'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canUsePosts($user), 403, 'Halaman ini khusus akun Event Organizer dan Perusahaan.');

        $data = $this->validated($request);
        $description = trim((string) ($data['description'] ?? ''));

        if ($description === '' && ! $request->hasFile('image')) {
            throw ValidationException::withMessages([
                'description' => 'Tulis teks postingan atau unggah satu gambar.',
            ]);
        }

        $post = new Post;
        $post->idUser = $user->id;
        $post->description = $description;

        if ($request->hasFile('image')) {
            $post->imagePath = $request->file('image')->store(
                $this->imageConfig()['directory'],
                $this->imageConfig()['disk'],
            );
        }

        $post->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Postingan berhasil dibuat.',
        ]);

        return to_route('posts.index', ['scope' => 'mine']);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorizePost($request->user(), $post);

        $data = $this->validated($request);
        $description = trim((string) ($data['description'] ?? ''));
        $removeImage = (bool) ($data['remove_image'] ?? false);

        $willHaveImage = $request->hasFile('image')
            || (filled($post->imagePath) && ! $removeImage);

        if ($description === '' && ! $willHaveImage) {
            throw ValidationException::withMessages([
                'description' => 'Tulis teks postingan atau pertahankan satu gambar.',
            ]);
        }

        $oldPath = $post->getRawOriginal('imagePath');
        $post->description = $description;

        if ($request->hasFile('image')) {
            $post->imagePath = $request->file('image')->store(
                $this->imageConfig()['directory'],
                $this->imageConfig()['disk'],
            );

            $this->deleteImage($oldPath);
        } elseif ($removeImage) {
            $post->imagePath = null;
            $this->deleteImage($oldPath);
        }

        $post->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Postingan berhasil diperbarui.',
        ]);

        return to_route('posts.index', ['scope' => $request->input('scope') === 'all' ? 'all' : 'mine']);
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorizePost($request->user(), $post);

        $this->deleteImage($post->getRawOriginal('imagePath'));

        $post->deleteAt = now();
        $post->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Postingan berhasil dihapus.',
        ]);

        return to_route('posts.index', ['scope' => 'mine']);
    }

    private function canUsePosts(?User $user): bool
    {
        return $user !== null && ($user->isEventOrganizer() || $user->isCompany());
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $image = $this->imageConfig();
        $maxMb = (int) round($image['max_kb'] / 1024);

        return $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => [
                'nullable',
                'file',
                'mimes:'.implode(',', $image['extensions']),
                'max:'.$image['max_kb'],
            ],
            'remove_image' => ['nullable', 'boolean'],
            'scope' => ['nullable', 'in:all,mine'],
        ], [
            'description.max' => 'Teks postingan maksimal 2000 karakter.',
            'image.mimes' => 'Gambar harus berformat '.implode(', ', $image['extensions']).'.',
            'image.max' => 'Ukuran gambar maksimal '.$maxMb.' MB.',
        ]);
    }

    /**
     * @return array{disk: string, directory: string, max_kb: int, extensions: array<int, string>}
     */
    private function imageConfig(): array
    {
        /** @var array{disk: string, directory: string, max_kb: int, extensions: array<int, string>} $config */
        $config = config('sponsormatch.post_image', [
            'disk' => 'public',
            'directory' => 'posts',
            'max_kb' => 4096,
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ]);

        return $config;
    }

    private function deleteImage(?string $path): void
    {
        if (filled($path)) {
            Storage::disk($this->imageConfig()['disk'])->delete($path);
        }
    }

    private function authorizePost(?User $user, Post $post): void
    {
        abort_unless($this->canUsePosts($user), 403);

        abort_if($post->deleteAt !== null, 404);

        abort_unless($post->isOwnedBy($user), 403, 'Anda tidak berhak mengubah postingan ini.');
    }
}
