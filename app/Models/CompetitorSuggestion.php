<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitorSuggestion extends Model
{
    protected $fillable = ['brand_id', 'domain', 'name', 'sources', 'classification', 'score', 'shared_terms', 'query_hits', 'reason', 'homepage_title', 'status'];

    protected $casts = ['sources' => 'array', 'shared_terms' => 'array'];
}
