<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $parent = $this->route('leave') ?? $this->route('travel');

        return $parent !== null && $this->user()?->can('processAdvance', $parent) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
