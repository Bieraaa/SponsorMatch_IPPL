<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi formulir "Profil Perusahaan".
 *
 * Hanya akun perusahaan yang boleh mengubah profilnya sendiri. Profil
 * inilah sumber utama rekomendasi AI, sehingga minimal nama dan bidang
 * industri wajib diisi.
 */
class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCompany() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $logo = config('sponsormatch.company_logo');

        return [
            'company_name' => ['required', 'string', 'max:100'],
            'industry' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'website' => ['nullable', 'url', 'max:255'],
            'sponsorship_min' => ['nullable', 'integer', 'min:0'],
            'sponsorship_max' => ['nullable', 'integer', 'min:0', 'gte:sponsorship_min'],
            'event_types' => ['nullable', 'string', 'max:500'],
            'target_audience' => ['nullable', 'string', 'max:500'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:kategori,idKategori'],
            'logo' => [
                'nullable',
                'image',
                'mimes:'.implode(',', $logo['extensions']),
                'max:'.$logo['max_kb'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'industry.required' => 'Bidang industri wajib diisi.',
            'sponsorship_max.gte' => 'Dana maksimum tidak boleh lebih kecil dari dana minimum.',
            'logo.max' => 'Ukuran logo terlalu besar.',
        ];
    }
}
