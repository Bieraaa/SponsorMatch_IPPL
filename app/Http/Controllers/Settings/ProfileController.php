<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $photo = config('sponsormatch.profile_photo');

        return Inertia::render('profile/Index', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'profilePhoto' => [
                'maxSizeKb' => $photo['max_kb'],
                'extensions' => $photo['extensions'],
                'mimetypes' => $photo['mimetypes'],
            ],
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * Akun SELALU diambil dari `$request->user()`, tidak pernah dari input
     * klien. Hanya `name`, `nomor_telepon`, dan `avatar` yang bisa berubah;
     * email, role, status subscription, token, dan isActive tidak pernah
     * disentuh di sini.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $avatarFile = $request->file('avatar');

        // Path mentah (bukan URL hasil accessor) — dipakai untuk menghapus
        // file lama dari storage setelah penyimpanan baru berhasil.
        $oldAvatarPath = $user->getRawOriginal('avatar');
        $newAvatarPath = null;

        // `avatar` tidak boleh ikut mass assignment karena isinya UploadedFile.
        $user->fill(Arr::except($validated, ['avatar']));

        if ($avatarFile !== null) {
            $directory = config('sponsormatch.profile_photo.directory', 'avatars')
                .'/'.$user->getKey();
            $disk = config('sponsormatch.profile_photo.disk', 'public');

            $newAvatarPath = $avatarFile->store($directory, $disk);

            if ($newAvatarPath === false) {
                throw ValidationException::withMessages([
                    'avatar' => 'Foto gagal disimpan. Silakan coba lagi.',
                ]);
            }

            $user->avatar = $newAvatarPath;
        }

        try {
            $user->save();
        } catch (Throwable $e) {
            // Jangan tinggalkan file yatim kalau penyimpanan DB gagal.
            if ($newAvatarPath !== null) {
                $this->deleteAvatarFile($newAvatarPath, $user->getKey());
            }

            throw $e;
        }

        // Baru setelah foto baru tersimpan permanen, foto lama dibersihkan.
        if ($newAvatarPath !== null && $oldAvatarPath !== null && $oldAvatarPath !== $newAvatarPath) {
            $this->deleteAvatarFile($oldAvatarPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profil berhasil diperbarui.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $avatarPath = $user->getRawOriginal('avatar');

        Auth::logout();

        $user->delete();

        // Setelah akun hilang, file fotonya (kalau ada) ikut dibersihkan.
        // Helper memastikan file yang masih dipakai akun lain tidak dihapus.
        $this->deleteAvatarFile($avatarPath);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Hapus file foto profil dari disk `public` dengan aman.
     *
     * Aturan:
     *  - hanya path relatif di dalam folder foto profil aplikasi;
     *  - file TIDAK dihapus kalau masih direferensikan akun lain;
     *  - nilai kosong / URL eksternal (mis. avatar Google) diabaikan.
     */
    private function deleteAvatarFile(?string $path, ?int $exceptUserId = null): void
    {
        if (blank($path)) {
            return;
        }

        $directory = config('sponsormatch.profile_photo.directory', 'avatars');

        // Jangan pernah menyentuh file di luar folder foto profil.
        if (! str_starts_with($path, $directory.'/')) {
            return;
        }

        $stillUsed = User::query()
            ->where('avatar', $path)
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->exists();

        if ($stillUsed) {
            return;
        }

        Storage::disk(config('sponsormatch.profile_photo.disk', 'public'))->delete($path);
    }
}
