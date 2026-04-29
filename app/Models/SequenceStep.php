<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceStep extends Model
{
    protected $fillable = [
        'sequence_id',
        'ordem',
        'mensagem',
        'delay_hours',
        'tipo',
        'imagem',
    ];

    protected $casts = [
        'delay_hours' => 'integer',
        'ordem' => 'integer',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }
}
