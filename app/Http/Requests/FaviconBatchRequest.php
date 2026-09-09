<?php

namespace App\Http\Requests;

use App\Enums\FaviconFormat;
use App\Enums\FaviconTheme;
use App\Services\Favicons\DomainNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class FaviconBatchRequest extends FormRequest
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
            'domains' => ['required', 'string'],
            'sz' => ['sometimes', 'nullable', 'integer', 'min:'.config('favicons.min_size'), 'max:'.config('favicons.max_size')],
            'theme' => ['sometimes', 'nullable', 'string', Rule::in(['dark', 'light'])],
            'format' => ['sometimes', 'nullable', 'string', Rule::in(FaviconFormat::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->parseDomains((string) $this->input('domains', '')) === []) {
                $validator->errors()->add('domains', 'Provide at least one valid domain.');
            }
        });
    }

    /**
     * @return list<string>
     */
    public function domains(): array
    {
        return $this->parseDomains((string) $this->validated('domains'));
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

    /**
     * Raw entries from the domains parameter that are not usable domains.
     *
     * @return list<string>
     */
    public function invalidDomains(): array
    {
        $parts = preg_split('/\s*,\s*/', (string) $this->input('domains', '')) ?: [];
        $normalizer = $this->normalizer();
        $invalid = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $normalized = $normalizer->normalize($part);

            if ($normalized === '' || ! $normalizer->isValid($normalized)) {
                $invalid[$part] = $part;
            }
        }

        return array_values($invalid);
    }

    /**
     * @return list<string>
     */
    private function parseDomains(string $raw): array
    {
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $max = (int) config('favicons.batch_max_domains');
        $normalizer = $this->normalizer();
        $domains = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $normalized = $normalizer->normalize($part);

            if ($normalized === '' || ! $normalizer->isValid($normalized)) {
                continue;
            }

            $domains[$normalized] = $normalized;

            if (count($domains) >= $max) {
                break;
            }
        }

        return array_values($domains);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => $validator->errors()->first() ?: 'Invalid request',
            ], 422),
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
