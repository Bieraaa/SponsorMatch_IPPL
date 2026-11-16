<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pembuatan campaign perusahaan.
 */
class StoreCampaignRequest extends FormRequest
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
        return $this->campaignRules(required: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Nama campaign wajib diisi.',
            'description.required' => 'Deskripsi campaign wajib diisi.',
            'idKategori.required' => 'Kategori event wajib dipilih.',
            'budgetMin.required' => 'Anggaran minimum wajib diisi.',
            'budgetMax.required' => 'Anggaran maksimum wajib diisi.',
            'budgetMax.gte' => 'Anggaran maksimum tidak boleh lebih kecil dari minimum.',
            'periodEnd.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
        ];
    }

    /**
     * Aturan yang dipakai bersama oleh create & update.
     *
     * @return array<string, mixed>
     */
    protected function campaignRules(bool $required): array
    {
        $banner = config('sponsormatch.campaign_banner');
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'max:150'],
            'description' => [$presence, 'string', 'max:3000'],
            'idKategori' => [$presence, 'integer', 'exists:kategori,idKategori'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'target_audience' => ['nullable', 'string', 'max:500'],
            'budgetMin' => [$presence, 'integer', 'min:0'],
            'budgetMax' => [$presence, 'integer', 'min:0', 'gte:budgetMin'],
            'supportType' => ['nullable', 'string', 'max:150'],
            'benefits' => ['nullable', 'string', 'max:2000'],
            'estimatedParticipant' => ['nullable', 'integer', 'min:0'],
            'periodStart' => [$presence, 'date'],
            'periodEnd' => [$presence, 'date', 'after_or_equal:periodStart'],
            'status' => ['nullable', 'in:draft,active,close'],
            'banner' => [
                'nullable',
                'image',
                'mimes:'.implode(',', $banner['extensions']),
                'max:'.$banner['max_kb'],
            ],
        ];
    }
}
