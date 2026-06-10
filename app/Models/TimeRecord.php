<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Registro de fichaje (entrada/salida). Es un log inmutable:
 * una vez creado no se puede modificar ni borrar (cumplimiento legal RDL 8/2019).
 * Para "anular" un registro se usa el flag `corrected` + un registro de corrección.
 */
class TimeRecord extends Model
{
    protected $table = 'time_records';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // solo tiene created_at, gestionado a mano

    public const TYPE_CLOCK_IN  = 1; // entrada
    public const TYPE_CLOCK_OUT = 2; // salida

    protected $fillable = [
        'employee_id',
        'type',
        'recorded_at',
        'clock_method',
        'validation_method',
        'device_id',
        'clock_zone_id',
        'ip',
        'user_agent',
        'risk_score',
        'is_suspicious',
        'origin',
        'sync_id',
        'synced_at',
        'corrected',
        'original_record_id',
        'note',
        'record_hash',
        'previous_hash',
        'created_at',
    ];

    protected $casts = [
        'recorded_at'   => 'datetime',
        'synced_at'     => 'datetime',
        'created_at'    => 'datetime',
        'is_suspicious' => 'boolean',
        'corrected'     => 'boolean',
        'risk_score'    => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->id ??= (string) Str::ulid();
            $model->created_at ??= now();
        });

        // Inmutable: bloqueamos cualquier intento de update o delete
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    /** Etiqueta legible para la interfaz (en español). */
    public function getLabelAttribute(): string
    {
        return $this->type === self::TYPE_CLOCK_IN ? 'Entrada' : 'Salida';
    }

    /** Registros del día de hoy (por fecha de fichaje). */
    public function scopeRecordedToday(Builder $query): Builder
    {
        return $query->whereDate('recorded_at', today());
    }

    /** Registros vigentes (no sustituidos por una corrección). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('corrected', false);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function clockZone(): BelongsTo
    {
        return $this->belongsTo(ClockZone::class, 'clock_zone_id');
    }

    public function originalRecord(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_record_id');
    }
}
