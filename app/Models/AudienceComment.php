<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AudienceComment extends Model
{
    protected $fillable = ['brand_id', 'platform', 'remote_id', 'post_remote_id', 'body', 'likes', 'sentiment', 'is_question', 'commented_at'];

    protected $casts = ['is_question' => 'boolean', 'commented_at' => 'datetime'];
}
