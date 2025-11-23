<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'is_draft',
        'published_at',
        'user_id',
    ];

    protected $casts = [
        'is_draft' => 'boolean',
        'published_at' => 'datetime',
    ];

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_draft', 0)->where('published_at', '<=', now());
    }

    #[Scope]
    protected function draft(Builder $query): void
    {
        $query->where('is_draft', 1);
    }

    #[Scope]
    protected function scheduled(Builder $query): void
    {
        $query->where('is_draft', 0)->where('published_at', '>', now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
