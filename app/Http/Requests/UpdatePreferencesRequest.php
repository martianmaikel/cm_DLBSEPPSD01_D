<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', 'max:64', Rule::in(\DateTimeZone::listIdentifiers())],
            'locale' => ['required', 'string', Rule::in(['en', 'de'])],
            'wants_global_digest' => ['required', 'boolean'],
            'threads' => ['sometimes', 'array'],
            'threads.*.id' => ['required', 'integer', 'exists:conflict_threads,id'],
            'threads.*.wants_digest' => ['required', 'boolean'],
            'threads.*.wants_critical' => ['required', 'boolean'],
        ];
    }
}
