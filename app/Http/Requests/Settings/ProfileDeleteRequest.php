<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `confirmed` memastikan pengguna mengetik ulang password yang
            // sama pada field `password_confirmation`.
            'password' => [...$this->currentPasswordRules(), 'confirmed'],
        ];
    }

    /**
     * Pesan validasi dalam bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.current_password' => 'Password yang dimasukkan salah.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
