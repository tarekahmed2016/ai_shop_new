<?php

namespace App\Console\Commands;

use App\Models\RichTextImage;
use App\Services\RichTextImageReferenceScanner;
use App\Services\RichTextImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CleanupOrphanRichTextImagesCommand extends Command
{
    protected $signature = 'rich-text:cleanup-orphans {--hours=24 : Minimum age before an unreferenced upload may be removed}';

    protected $description = 'Remove rich-text image uploads that are no longer referenced in stored HTML content';

    public function handle(
        RichTextImageReferenceScanner $scanner,
        RichTextImageService $imageService,
    ): int {
        $cutoff = Carbon::now()->subHours((int) $this->option('hours'));
        $referencedPaths = $scanner->referencedPaths();
        $removed = 0;

        RichTextImage::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($images) use ($referencedPaths, $imageService, &$removed) {
                foreach ($images as $image) {
                    if (in_array($image->path, $referencedPaths, true)) {
                        continue;
                    }

                    $imageService->deleteFile($image);
                    $removed++;
                }
            });

        $this->info("Removed {$removed} orphan rich-text image(s).");

        return self::SUCCESS;
    }
}
