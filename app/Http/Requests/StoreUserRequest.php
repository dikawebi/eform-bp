<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rules;

class StoreUserRequest extends UserRequest
{
    public function rules(): array
    {
        return $this->baseRules() + [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class, 'ends_with:@borneoprima.com'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return ['email.ends_with' => 'Akun hanya dibuka untuk email korporat @borneoprima.com.'];
    }
}
