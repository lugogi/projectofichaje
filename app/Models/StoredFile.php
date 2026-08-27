<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StoredFile extends Model
{
    use SoftDeletes;

    public const ENTITY_CORRECTION_REQUEST = 'correction_request';

    public $timestamps = false;

    protected $table = 'files';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uploaded_by',
        'entity_type',
        'entity_id',
        'file_name',
        'storage_provider',
        'bucket',
        'storage_key',
        'mime_type',
        'extension',
        'size_bytes',
        'hash_sha256',
        'visibility',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }
}
