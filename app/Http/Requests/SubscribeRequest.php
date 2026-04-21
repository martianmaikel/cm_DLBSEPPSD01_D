<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'timezone' => ['required', 'string', 'max:64', Rule::in(\DateTimeZone::listIdentifiers())],
            'locale' => ['required', 'string', Rule::in(['en', 'de'])],
            'thread_ids' => ['sometimes', 'array'],
            'thread_ids.*' => ['integer', 'exists:conflict_threads,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'timezone.in' => 'The selected timezone is not a valid IANA timezone identifier.',
            'locale.in' => 'Only English (en) and German (de) are currently supported.',
        ];
    }
}
