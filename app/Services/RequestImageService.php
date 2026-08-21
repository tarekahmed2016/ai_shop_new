<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RequestImageService
{
    public function store(CustomerRequest $customerRequest, UploadedFile $image): RequestImage
    {
        $this->delete($customerRequest);

        $extension = strtolower($image->guessExtension() ?: $image->getClientOriginalExtension() ?: 'jpg');
        $filename = (string) Str::ulid().'.'.$extension;
        $path = $image->storeAs('customer-requests', $filename, RequestImage::DISK);

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Failed to store request image.');
        }

        try {
            $record = new RequestImage;
            $record->customer_request_id = $customerRequest->id;
            $record->path = $path;
            $record->original_name = $image->getClientOriginalName();
            $record->mime_type = $image->getMimeType();
            $record->size = $image->getSize();
            $record->save();
        } catch (\Throwable $exception) {
            Storage::disk(RequestImage::DISK)->delete($path);
            throw $exception;
        }

        return $record;
    }

    public function delete(CustomerRequest $customerRequest): void
    {
        $image = $customerRequest->image;

        if ($image === null) {
            return;
        }

        if ($image->path && Storage::disk(RequestImage::DISK)->exists($image->path)) {
            Storage::disk(RequestImage::DISK)->delete($image->path);
        }

        $image->delete();
    }
}
