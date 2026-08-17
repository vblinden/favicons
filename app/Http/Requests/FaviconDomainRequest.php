<?php

namespace App\Http\Requests;

use App\Services\Favicons\DomainNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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

    protected function prepareForValidation(): void
    {
        if ($this->query->has('sz') && $this->query('sz') === '') {
            $this->merge(['sz' => null]);
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
