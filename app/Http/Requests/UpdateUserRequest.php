<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rules;

class UpdateUserRequest extends UserRequest
{
    public function rules(): array
    {
        $target = $this->route('user');

        return $this->baseRules() + [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'ends_with:@borneoprima.com', 'unique:' . User::class . ',email,' . ($target instanceof User ? $target->getKey() : 'null') . ',id'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return ['email.ends_with' => 'Akun hanya dibuka untuk email korporat @borneoprima.com.'];
    }
}
