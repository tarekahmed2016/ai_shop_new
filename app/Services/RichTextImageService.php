<?php

namespace App\Services;

use App\Models\RichTextImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RichTextImageService
{
    public function store(UploadedFile $file, User $user): RichTextImage
    {
        $dimensions = @getimagesize($file->getPathname());
        $extension = strtolower($file->getClientOriginalExtension());
        $safeExtension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
        $filename = Str::uuid()->toString().'.'.$safeExtension;
        $directory = 'rich-text/'.now()->format('Y/m');
        $path = $file->storeAs($directory, $filename, 'public');

        return RichTextImage::create([
            'uploaded_by' => $user->id,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => is_array($dimensions) ? ($dimensions[0] ?: null) : null,
            'height' => is_array($dimensions) ? ($dimensions[1] ?: null) : null,
        ]);
    }

    public function deleteFile(RichTextImage $image): void
    {
        if ($image->path && Storage::disk($image->disk)->exists($image->path)) {
            Storage::disk($image->disk)->delete($image->path);
        }

        $image->delete();
    }
}
