<?php

namespace App\Services;

use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CorrectionRequestFileService
{
    public function storeForCorrectionRequest(
        UploadedFile $uploadedFile,
        string $correctionRequestId,
        string $uploadedBy,
    ): StoredFile {
        $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension());
        $fileName = Str::uuid().'.'.$extension;
        $directory = "correction-requests/{$correctionRequestId}";
        $storageKey = "{$directory}/{$fileName}";

        $uploadedFile->storeAs($directory, $fileName, 'local');

        return StoredFile::create([
            'uploaded_by' => $uploadedBy,
            'entity_type' => StoredFile::ENTITY_CORRECTION_REQUEST,
            'entity_id' => $correctionRequestId,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'storage_provider' => 'local',
            'bucket' => 'local',
            'storage_key' => $storageKey,
            'mime_type' => $uploadedFile->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $uploadedFile->getSize(),
            'hash_sha256' => hash_file('sha256', $uploadedFile->getRealPath()),
            'visibility' => 'private',
            'status' => 'stored',
        ]);
    }

    public function absolutePath(StoredFile $file): string
    {
        return Storage::disk('local')->path($file->storage_key);
    }
}
