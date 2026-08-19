<?php

namespace App\Services;

use App\Models\CertificateAward;
use App\Models\CompanyInfo;
use App\Models\HeroSlide;
use App\Models\HomepagePromoBlock;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\TeamMember;

class RichTextImageReferenceScanner
{
    /**
     * @return list<string>
     */
    public function referencedPaths(): array
    {
        $paths = [];

        foreach ($this->richTextValues() as $html) {
            if (! is_string($html) || $html === '') {
                continue;
            }

            if (preg_match_all('#/storage/(rich-text/[^"\'\s>]+)#', $html, $matches)) {
                foreach ($matches[1] as $path) {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return \Generator<int, string|null>
     */
    private function richTextValues(): \Generator
    {
        $companyInfoFields = [
            'hero_description_ar', 'hero_description_en',
            'about_ar', 'about_en', 'vision_ar', 'vision_en', 'mission_ar', 'mission_en',
        ];

        $descriptionFields = ['description_ar', 'description_en'];
        $bioFields = ['bio_ar', 'bio_en'];
        $pageFields = ['content_ar', 'content_en'];

        $companyInfo = CompanyInfo::query()->first();
        if ($companyInfo) {
            foreach ($companyInfoFields as $field) {
                yield $companyInfo->{$field};
            }
        }

        foreach ([Service::class, Project::class, HeroSlide::class, HomepagePromoBlock::class, CertificateAward::class] as $modelClass) {
            foreach ($modelClass::query()->cursor() as $record) {
                foreach ($descriptionFields as $field) {
                    yield $record->{$field};
                }
            }
        }

        foreach (TeamMember::query()->cursor() as $member) {
            foreach ($bioFields as $field) {
                yield $member->{$field};
            }
        }

        foreach (Page::query()->cursor() as $page) {
            foreach ($pageFields as $field) {
                yield $page->{$field};
            }
        }
    }
}
