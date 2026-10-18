<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaEvidence extends Model
{
    protected $table = 'persona_evidence';
    protected $fillable = ['persona_id', 'kind', 'note', 'observed_on'];
    protected $casts = ['observed_on' => 'date'];

    public const KINDS = [
        'interview' => 'Customer interview', 'survey' => 'Survey response', 'support' => 'Support / sales conversation',
        'lead_data' => 'Lead data', 'analytics' => 'Analytics', 'listening' => 'Social listening', 'other' => 'Other',
    ];

    public function persona(): BelongsTo { return $this->belongsTo(Persona::class); }
}
