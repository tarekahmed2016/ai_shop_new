<?php

namespace App\Services;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MerchantOfferImageService
{
    public const MAX_IMAGES = 5;

    /**
     * @param  list<UploadedFile>  $files
     * @return list<string> stored paths (for rollback)
     */
    public function storeMany(MerchantOffer $offer, array $files, int $startingSortOrder = 0): array
    {
        $storedPaths = [];
        $sortOrder = $startingSortOrder;

        try {
            foreach ($files as $file) {
                $this->assertSafeRaster($file);
                $storedPaths[] = $this->storeOne($offer, $file, $sortOrder);
                $sortOrder++;
            }
        } catch (\Throwable $exception) {
            $this->deletePaths($storedPaths);
            throw $exception;
        }

        return $storedPaths;
    }

    public function storeOne(MerchantOffer $offer, UploadedFile $image, int $sortOrder): string
    {
        $this->assertSafeRaster($image);

        $extension = strtolower($image->guessExtension() ?: $image->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages([
                'images' => 'Only JPEG, PNG, and WEBP images are allowed.',
            ]);
        }

        $filename = (string) Str::ulid().'.'.$extension;
        $directory = 'merchant-offers/'.$offer->public_id;
        $path = $image->storeAs($directory, $filename, MerchantOfferImage::DISK);

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Failed to store offer image.');
        }

        try {
            $record = new MerchantOfferImage;
            $record->merchant_offer_id = $offer->id;
            $record->path = $path;
            $record->original_name = $image->getClientOriginalName();
            $record->mime_type = $image->getMimeType();
            $record->size = $image->getSize();
            $record->sort_order = $sortOrder;
            $record->save();
        } catch (\Throwable $exception) {
            Storage::disk(MerchantOfferImage::DISK)->delete($path);
            throw $exception;
        }

        return $path;
    }

    public function deleteImage(MerchantOfferImage $image): void
    {
        if ($image->path && Storage::disk(MerchantOfferImage::DISK)->exists($image->path)) {
            Storage::disk(MerchantOfferImage::DISK)->delete($image->path);
        }

        $image->delete();
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteByIds(MerchantOffer $offer, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $images = $offer->images()->whereIn('id', $ids)->get();

        foreach ($images as $image) {
            $this->deleteImage($image);
        }
    }

    /**
     * @param  list<string>  $paths
     */
    public function deletePaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && Storage::disk(MerchantOfferImage::DISK)->exists($path)) {
                Storage::disk(MerchantOfferImage::DISK)->delete($path);
            }
        }
    }

    private function assertSafeRaster(UploadedFile $image): void
    {
        $mime = (string) $image->getMimeType();
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'images' => 'Only JPEG, PNG, and WEBP images are allowed.',
            ]);
        }

        $dimensions = @getimagesize($image->getPathname());
        if ($dimensions === false) {
            throw ValidationException::withMessages([
                'images' => 'The uploaded file is not a valid image.',
            ]);
        }
    }
}
