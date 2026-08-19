<?php

namespace App\Http\Controllers;

use App\Http\Requests\RichTextImageUploadRequest;
use App\Services\RichTextImageService;
use Illuminate\Http\JsonResponse;

class RichTextImageController extends Controller
{
    public function __construct(public RichTextImageService $richTextImageService) {}

    public function store(RichTextImageUploadRequest $request): JsonResponse
    {
        $image = $this->richTextImageService->store(
            file: $request->file('upload'),
            user: $request->user(),
        );

        return response()->json([
            'url' => $image->url,
        ]);
    }
}
