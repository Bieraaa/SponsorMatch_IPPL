<?php

namespace App\Http\Requests\Company;

/**
 * Validasi perubahan campaign. Field yang dikirim saja yang divalidasi,
 * sehingga status bisa diubah tanpa mengirim ulang seluruh isian.
 */
class UpdateCampaignRequest extends StoreCampaignRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->campaignRules(required: false);
    }
}
