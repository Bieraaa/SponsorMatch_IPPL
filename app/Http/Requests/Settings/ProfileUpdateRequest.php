<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * Hanya field yang memang boleh diubah pemilik akun yang divalidasi.
     * `email`, `role`, `status_subscription`, `isActive`, dan field sensitif
     * lain SENGAJA tidak ada di sini sehingga tidak akan pernah masuk ke
     * `$request->validated()` — walaupun klien mengirimkannya.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $config = config('sponsormatch.profile_photo');

        return [
            'name' => $this->nameRules(),
            'nomor_telepon' => [
                'nullable',
                'string',
                'max:30',
                // Format internasional: boleh diawali "+", angka, spasi,
                // titik, tanda hubung, dan tanda kurung. Minimal 6 digit.
                'regex:/^\+?[0-9][0-9\s().-]{5,29}$/',
            ],
            'avatar' => [
                'nullable',
                'image',
                'mimes:'.implode(',', $config['extensions']),
                'mimetypes:'.implode(',', $config['mimetypes']),
                'max:'.$config['max_kb'],
                // Pemeriksaan isi file yang sesungguhnya. `image`/`mimes`
                // memakai MIME yang dilaporkan file (bisa dipalsukan lewat
                // ekstensi), sedangkan getimagesize() membaca header binary
                // asli. Dengan ini file PHP/teks yang diganti namanya menjadi
                // .jpg tetap ditolak.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! $value->isValid()) {
                        $fail('Foto gagal diunggah. Silakan pilih file lain.');

                        return;
                    }

                    $info = @getimagesize($value->getRealPath());
                    $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG];

                    if ($info === false || ! in_array($info[2], $allowedTypes, true)) {
                        $fail('File yang dipilih bukan gambar JPG atau PNG yang valid.');
                    }
                },
            ],
        ];
    }

    /**
     * Pesan validasi yang mudah dipahami pengguna.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 255 karakter.',
            'nomor_telepon.max' => 'Nomor telepon maksimal 30 karakter.',
            'nomor_telepon.regex' => 'Format nomor telepon tidak valid. Contoh: +62 812-3456-7890.',
            'avatar.image' => 'File yang dipilih bukan gambar yang valid.',
            'avatar.mimes' => 'Format foto harus JPG, JPEG, atau PNG.',
            'avatar.mimetypes' => 'Format foto harus JPG, JPEG, atau PNG.',
            'avatar.max' => 'Ukuran foto maksimal :max KB.',
            'avatar.uploaded' => 'Foto gagal diunggah. Pastikan ukuran file tidak melebihi batas server.',
        ];
    }
}
