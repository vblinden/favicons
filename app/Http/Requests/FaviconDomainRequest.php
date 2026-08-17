<?php

namespace App\Http\Requests;

use App\Enums\FaviconTheme;
use App\Services\Favicons\DomainNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class FaviconDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sz' => ['sometimes', 'nullable', 'integer', 'min:'.config('favicons.min_size'), 'max:'.config('favicons.max_size')],
            'theme' => ['sometimes', 'nullable', 'string', Rule::in(['dark', 'light'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $domain = (string) $this->route('domain');

            if (! $this->normalizer()->isValid($domain)) {
                $validator->errors()->add('domain', 'The domain is invalid.');
            }
        });
    }

    public function domain(): string
    {
        return $this->normalizer()->normalize((string) $this->route('domain'));
    }

    public function size(): int
    {
        $size = $this->validated('sz');

        return $size === null ? (int) config('favicons.default_size') : (int) $size;
    }

    public function theme(): FaviconTheme
    {
        $theme = $this->validated('theme');

        return $theme === null
            ? FaviconTheme::Default
            : FaviconTheme::from($theme);
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->has('sz') && $this->query('sz') === '') {
            $this->merge(['sz' => null]);
        }

        if ($this->query->has('theme') && $this->query('theme') === '') {
            $this->merge(['theme' => null]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response($validator->errors()->first() ?: 'Invalid request', 422),
        );
    }

    private function normalizer(): DomainNormalizer
    {
        return $this->container->make(DomainNormalizer::class);
    }
}
