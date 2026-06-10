<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Sesión de trabajo: empareja una entrada con su salida.
 * Se deriva de los time_records. El campo `status` cubre el ciclo de vida.
 */
class WorkSession extends Model
{
    protected $table = 'work_sessions';
    public $incrementing = false;
    protected $keyType = 'string';

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'employee_id',
        'clock_in_record_id',
        'clock_out_record_id',
        'clocked_in_at',
        'clocked_out_at',
        'status',
        'processed',
    ];

    protected $casts = [
        'clocked_in_at'  => 'datetime',
        'clocked_out_at' => 'datetime',
        'processed'      => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->id ??= (string) Str::ulid());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function clockInRecord(): BelongsTo
    {
        return $this->belongsTo(TimeRecord::class, 'clock_in_record_id');
    }

    public function clockOutRecord(): BelongsTo
    {
        return $this->belongsTo(TimeRecord::class, 'clock_out_record_id');
    }
}
