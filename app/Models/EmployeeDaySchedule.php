<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeDaySchedule extends Model
{
    use SoftDeletes;

    protected $table = 'employee_day_schedule';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->id ??= (string) Str::ulid());
    }

    protected $fillable = [
        'id',
        'employee_id',
        'weekday',
        'start_time',
        'end_time',
        'active',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function employee(): BelongsTo
    {
    	return $this->belongsTo(Employee::class, 'employee_id');
    }
}
