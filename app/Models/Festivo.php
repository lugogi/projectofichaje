<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Festivo extends Model
{
    protected $table = 'festivos';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;

    protected $fillable = ['calendario_laboral_id', 'nombre', 'fecha', 'tipo', 'obligatorio'];

    protected $casts = [
        'fecha'      => 'date',
        'obligatorio' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn($m) => $m->id ??= (string) Str::ulid());
    }

    public function calendario(): BelongsTo
    {
        return $this->belongsTo(CalendarioLaboral::class, 'calendario_laboral_id');
    }
}
    