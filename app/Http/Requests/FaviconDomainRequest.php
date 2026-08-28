<?php

namespace App\Http\Requests;

use App\Enums\FaviconFormat;
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
            'format' => ['sometimes', 'nullable', 'string', Rule::in(FaviconFormat::values())],
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

    public function imageFormat(): FaviconFormat
    {
        $format = $this->validated('format');

        if (is_string($format) && $format !== '') {
            return FaviconFormat::from($format);
        }

        return $this->formatFromAccept() ?? FaviconFormat::Png;
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->has('sz') && $this->query('sz') === '') {
            $this->merge(['sz' => null]);
        }

        if ($this->query->has('theme') && $this->query('theme') === '') {
            $this->merge(['theme' => null]);
        }

        if ($this->query->has('format') && $this->query('format') === '') {
            $this->merge(['format' => null]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response($validator->errors()->first() ?: 'Invalid request', 422),
        );
    }

    private function formatFromAccept(): ?FaviconFormat
    {
        $accept = strtolower((string) $this->header('Accept', ''));

        if ($accept === '' || str_contains($accept, '*/*')) {
            return null;
        }

        if (str_contains($accept, 'image/webp') && ! str_contains($accept, 'image/png')) {
            return FaviconFormat::Webp;
        }

        return null;
    }

    private function normalizer(): DomainNormalizer
    {
        return $this->container->make(DomainNormalizer::class);
    }
}
