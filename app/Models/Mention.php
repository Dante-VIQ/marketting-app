<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mention extends Model
{
    protected $fillable = [
        'brand_id', 'listening_query_id', 'source', 'url_hash', 'title', 'url', 'snippet', 'author', 'publisher',
        'published_at', 'score', 'comments', 'sentiment', 'is_dismissed',
    ];

    protected $casts = ['published_at' => 'datetime', 'is_dismissed' => 'boolean'];

    public function listeningQuery(): BelongsTo
    {
        return $this->belongsTo(ListeningQuery::class, 'listening_query_id');
    }
}
