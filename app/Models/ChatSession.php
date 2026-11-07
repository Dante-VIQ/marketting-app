<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    protected $fillable = [
        'brand_id',
        'user_id',
        'mode',
        'metadata',
        'last_active_at',
    ];

    protected $casts = [
        'metadata'       => 'array',
        'last_active_at' => 'datetime',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id')->orderBy('created_at');
    }

    /**
     * Get the last N messages as a simple array for prompt history.
     */
    public function recentHistory(int $limit = 10): array
    {
        return $this->hasMany(ChatMessage::class, 'session_id')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->toArray();
    }

    public function touch($attribute = null): bool
    {
        $this->last_active_at = now();
        return $this->save();
    }
}
