<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAffiliateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('affiliate')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('newsletter_affiliates', 'slug')->ignore($id)],
            'headline_en' => ['required', 'string', 'max:255'],
            'headline_de' => ['required', 'string', 'max:255'],
            'body_en' => ['nullable', 'string', 'max:1000'],
            'body_de' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'target_url' => ['required', 'url', 'max:2048'],
            'cta_en' => ['nullable', 'string', 'max:60'],
            'cta_de' => ['nullable', 'string', 'max:60'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
