<?php

namespace App\Services;

use App\Models\CompanyInfo;
use App\Support\ThemeColor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanyInfoService
{
    /**
     * @var list<string>
     */
    private const COMPANY_INFO_FIELDS = [
        'name_ar',
        'name_en',
        'phone',
        'email',
        'hero_title_ar',
        'hero_title_en',
        'hero_description_ar',
        'hero_description_en',
        'about_ar',
        'about_en',
        'vision_ar',
        'vision_en',
        'mission_ar',
        'mission_en',
        'address_ar',
        'address_en',
        'website',
        'facebook',
        'instagram',
        'linkedin',
        'x_twitter',
        'youtube',
        'tiktok',
        'snapchat',
        'whatsapp',
    ];

    /**
     * @var list<string>
     */
    private const THEME_FIELDS = [
        'theme_primary_color',
        'theme_dark_color',
        'theme_heading_text_color',
        'theme_body_text_color',
        'theme_muted_text_color',
        'theme_nav_text_color',
        'theme_nav_hover_text_color',
        'theme_hero_text_color',
        'theme_on_dark_text_color',
    ];

    /**
     * @var list<string>
     */
    private const CUSTOM_ASSET_FIELDS = [
        'custom_css',
        'custom_js',
    ];

    /**
     * @var list<string>
     */
    private const STRING_FIELDS = [
        ...self::COMPANY_INFO_FIELDS,
        ...self::THEME_FIELDS,
        ...self::CUSTOM_ASSET_FIELDS,
    ];

    public function __construct(public ActivityLogService $activityLogService) {}

    public function getCompanyInfo(): CompanyInfo
    {
        $companyInfo = CompanyInfo::with('attachment')->first();

        if (! $companyInfo) {
            return new CompanyInfo($this->emptyDefaults());
        }

        $companyInfo->fill($this->normalizeStringFields($companyInfo));

        return $companyInfo;
    }

    /**
     * @return array<string, string>
     */
    public function getThemeColors(): array
    {
        $companyInfo = CompanyInfo::first() ?? new CompanyInfo($this->emptyDefaults());

        return ThemeColor::resolvedFor($companyInfo);
    }

    /**
     * @return array<string, string>
     */
    public function getCustomAssets(): array
    {
        $companyInfo = CompanyInfo::first();

        return [
            'custom_css' => $companyInfo?->custom_css ?? '',
            'custom_js' => $companyInfo?->custom_js ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(?CompanyInfo $companyInfo, array $data, ?UploadedFile $logo = null): CompanyInfo
    {
        return $this->persist(
            companyInfo: $companyInfo,
            data: $data,
            allowedFields: self::COMPANY_INFO_FIELDS,
            logo: $logo,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateThemeColors(?CompanyInfo $companyInfo, array $data): CompanyInfo
    {
        return $this->persist(
            companyInfo: $companyInfo,
            data: $data,
            allowedFields: self::THEME_FIELDS,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCustomAssets(?CompanyInfo $companyInfo, array $data): CompanyInfo
    {
        return $this->persist(
            companyInfo: $companyInfo,
            data: $data,
            allowedFields: self::CUSTOM_ASSET_FIELDS,
        );
    }

    /**
     * @return array<string, string>
     */
    public function emptyDefaults(): array
    {
        return [
            ...array_fill_keys(array_diff(self::STRING_FIELDS, ThemeColor::fieldNames()), ''),
            ...ThemeColor::DEFAULTS,
        ];
    }

    /**
     * @param  list<string>  $allowedFields
     * @param  array<string, mixed>  $data
     */
    private function persist(?CompanyInfo $companyInfo, array $data, array $allowedFields, ?UploadedFile $logo = null): CompanyInfo
    {
        if (! $companyInfo?->exists) {
            $companyInfo = CompanyInfo::create($data);

            if ($logo) {
                $this->storeLogo(companyInfo: $companyInfo, logo: $logo);
            }

            $this->activityLogService->recordCreated(
                subject: $companyInfo,
                allowedFields: $allowedFields,
                subjectLabel: $this->subjectLabel($companyInfo),
            );

            return $companyInfo;
        }

        $originalValues = $companyInfo->only($allowedFields);

        $companyInfo->update($data);

        if ($logo) {
            $this->deleteLogo(companyInfo: $companyInfo);
            $this->storeLogo(companyInfo: $companyInfo, logo: $logo);
        }

        $this->activityLogService->recordChanges(
            subject: $companyInfo,
            originalValues: $originalValues,
            allowedFields: $allowedFields,
            subjectLabel: $this->subjectLabel($companyInfo),
        );

        return $companyInfo;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeStringFields(CompanyInfo $companyInfo): array
    {
        $resolvedTheme = ThemeColor::resolvedFor($companyInfo);
        $normalized = [];

        foreach (self::STRING_FIELDS as $field) {
            if (in_array($field, self::CUSTOM_ASSET_FIELDS, true)) {
                $normalized[$field] = $companyInfo->{$field} ?? '';

                continue;
            }

            $normalized[$field] = $resolvedTheme[$field] ?? ($companyInfo->{$field} ?? '');
        }

        return $normalized;
    }

    private function subjectLabel(CompanyInfo $companyInfo): string
    {
        return $companyInfo->name_ar ?: $companyInfo->name_en ?: 'Company Info';
    }

    private function storeLogo(CompanyInfo $companyInfo, UploadedFile $logo): void
    {
        $path = $logo->store('company-info', 'public');
        $companyInfo->attachment()->create([
            'name' => $logo->getClientOriginalName(),
            'path' => $path,
        ]);
    }

    private function deleteLogo(CompanyInfo $companyInfo): void
    {
        $attachment = $companyInfo->attachment;
        if ($attachment && $attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }
        if ($attachment) {
            $attachment->delete();
        }
    }
}
