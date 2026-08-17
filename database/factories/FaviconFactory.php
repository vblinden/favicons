<?php

namespace Database\Factories;

use App\Models\Favicon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favicon>
 */
class FaviconFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'domain' => $domain,
            'theme' => 'default',
            'source_url' => 'https://'.$domain.'/favicon.ico',
            'storage_path' => hash('sha256', $domain.'|default').'.png',
            'content_type' => 'image/png',
            'width' => 32,
            'height' => 32,
            'status' => 'ok',
            'fetched_at' => now(),
            'request_count' => 0,
        ];
    }

    public function requested(int $times = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'request_count' => $times,
        ]);
    }

    public function dark(): static
    {
        return $this->state(fn (array $attributes) => [
            'theme' => 'dark',
            'storage_path' => hash('sha256', ($attributes['domain'] ?? 'example.com').'|dark').'.png',
        ]);
    }

    public function light(): static
    {
        return $this->state(fn (array $attributes) => [
            'theme' => 'light',
            'storage_path' => hash('sha256', ($attributes['domain'] ?? 'example.com').'|light').'.png',
        ]);
    }

    public function fallback(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_url' => null,
            'status' => 'fallback',
        ]);
    }
}
