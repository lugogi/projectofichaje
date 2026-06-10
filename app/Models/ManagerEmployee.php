<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ManagerEmployee extends Model
{
    protected $table = 'manager_employees';

    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->id ??= (string) Str::ulid());
    }

    protected $fillable = [
        'employee_id',
        'manager_id',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function scopeActive($query)
    {
        $today = today()->toDateString();

        return $query
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            });
    }
}
