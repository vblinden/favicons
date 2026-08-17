<?php

namespace App\Models;

use Database\Factories\FaviconFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'domain',
    'theme',
    'source_url',
    'storage_path',
    'content_type',
    'width',
    'height',
    'status',
    'fetched_at',
])]
class Favicon extends Model
{
    /** @use HasFactory<FaviconFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'theme' => 'default',
        'status' => 'fallback',
        'content_type' => 'image/png',
        'request_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'request_count' => 'integer',
            'fetched_at' => 'datetime',
        ];
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    public function isFallback(): bool
    {
        return $this->status === 'fallback';
    }

    public function recordRequest(): void
    {
        static::query()->whereKey($this->getKey())->increment('request_count');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function mostRequested(Builder $query): Builder
    {
        return $query
            ->selectRaw('domain, SUM(request_count) as request_count')
            ->groupBy('domain')
            ->havingRaw('SUM(request_count) > 0')
            ->orderByDesc('request_count')
            ->orderBy('domain');
    }
}
