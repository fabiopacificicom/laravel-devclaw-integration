<?php

namespace Pacificdev\DevClaw\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class CalendarEntry extends Model
{
    protected $fillable = [
        'title',
        'type',
        'status',
        'scheduled_at',
        'color',
        'linkable_type',
        'linkable_id',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeInRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('scheduled_at', [$from, $to]);
    }
}
