<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EvidenceAttachment;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;

class EvidenceAvailability
{
    public function isIntact(EvidenceAttachment $attachment): bool
    {
        return $this->isIntactFile($attachment->storage_path, $attachment->byte_size, $attachment->sha256);
    }

    public function isIntactFile(string $path, int $size, string $sha256): bool
    {
        try {
            $disk = Storage::disk('evidence');
            if (! $disk->exists($path)) {
                return false;
            }
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                return false;
            }
            try {
                $hash = hash_init('sha256');
                $bytes = hash_update_stream($hash, $stream);

                return $bytes === $size && hash_equals($sha256, hash_final($hash));
            } finally {
                fclose($stream);
            }
        } catch (FilesystemException) {
            return false;
        }
    }
}
