<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdInsightRow extends Model
{
    protected $fillable = ['brand_id', 'ad_account_id', 'breakdown', 'dim1', 'dim2', 'label', 'date_start', 'date_stop',
        'impressions', 'reach', 'clicks', 'spend', 'results', 'currency', 'row_hash'];

    protected $casts = ['date_start' => 'date', 'date_stop' => 'date', 'spend' => 'decimal:2'];
}
