<?php

namespace App\Models;

use Database\Factories\FaviconFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'domain',
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
        'status' => 'fallback',
        'content_type' => 'image/png',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
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
}
