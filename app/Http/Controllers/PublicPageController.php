<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\PublicHomeService;
use Inertia\Inertia;
use Inertia\Response;

class PublicPageController extends Controller
{
    public function __construct(public PublicHomeService $publicHomeService) {}

    public function show(string $slug): Response
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return Inertia::render('Public/CustomPage', [
            'companyInfo' => $this->publicHomeService->getPublicCompanyInfo(),
            'page' => [
                'title_ar' => $page->title_ar,
                'title_en' => $page->title_en,
                'content_ar' => $page->content_ar,
                'content_en' => $page->content_en,
                'slug' => $page->slug,
            ],
        ]);
    }
}
