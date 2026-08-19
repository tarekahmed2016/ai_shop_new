<?php

namespace App\Services;

use App\Enums\CertificateAwardType;
use App\Enums\ClientPartnerType;
use App\Models\CertificateAward;
use App\Models\ClientPartner;
use App\Models\Project;
use App\Models\Service;
use App\Models\TeamMember;
use App\Support\ThemeColor;
use Illuminate\Support\Collection;

class PublicHomeService
{
    public function __construct(
        public CompanyInfoService $companyInfoService,
        public HeroSlideService $heroSlideService,
        public HomepagePromoBlockService $homepagePromoBlockService,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function getPublicCompanyInfo(): array
    {
        $companyInfo = $this->companyInfoService->getCompanyInfo();
        $theme = ThemeColor::resolvedFor($companyInfo);

        return [
            'name_ar' => $companyInfo->name_ar ?? '',
            'name_en' => $companyInfo->name_en ?? '',
            'phone' => $companyInfo->phone ?? '',
            'email' => $companyInfo->email ?? '',
            'hero_title_ar' => $companyInfo->hero_title_ar ?? '',
            'hero_title_en' => $companyInfo->hero_title_en ?? '',
            'hero_description_ar' => $companyInfo->hero_description_ar ?? '',
            'hero_description_en' => $companyInfo->hero_description_en ?? '',
            'about_ar' => $companyInfo->about_ar ?? '',
            'about_en' => $companyInfo->about_en ?? '',
            'vision_ar' => $companyInfo->vision_ar ?? '',
            'vision_en' => $companyInfo->vision_en ?? '',
            'mission_ar' => $companyInfo->mission_ar ?? '',
            'mission_en' => $companyInfo->mission_en ?? '',
            'address_ar' => $companyInfo->address_ar ?? '',
            'address_en' => $companyInfo->address_en ?? '',
            'website' => $companyInfo->website ?? '',
            'facebook' => $companyInfo->facebook ?? '',
            'instagram' => $companyInfo->instagram ?? '',
            'linkedin' => $companyInfo->linkedin ?? '',
            'x_twitter' => $companyInfo->x_twitter ?? '',
            'youtube' => $companyInfo->youtube ?? '',
            'tiktok' => $companyInfo->tiktok ?? '',
            'snapchat' => $companyInfo->snapchat ?? '',
            'whatsapp' => $companyInfo->whatsapp ?? '',
            ...$theme,
            'custom_css' => $companyInfo->custom_css ?? '',
            'custom_js' => $companyInfo->custom_js ?? '',
            'logo' => $companyInfo->attachment?->asset_path,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getActiveHeroSlides(): Collection
    {
        return $this->heroSlideService->getActiveSlidesForPublic();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveBusinessCta(): ?array
    {
        return $this->homepagePromoBlockService->getActiveBusinessCtaForPublic();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveFeatureBand(): ?array
    {
        return $this->homepagePromoBlockService->getActiveFeatureBandForPublic();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getActivePromoStrips(): Collection
    {
        return $this->homepagePromoBlockService->getActivePromoStripsForPublic();
    }

    /**
     * @return Collection<int, array{name_ar: string, name_en: string, description_ar: string|null, description_en: string|null, image: string|null}>
     */
    public function getActiveServices(): Collection
    {
        return Service::query()
            ->with('attachment')
            ->where('is_active', true)
            ->orderBy('ordering')
            ->get()
            ->map(fn (Service $service) => [
                'name_ar' => $service->name_ar,
                'name_en' => $service->name_en,
                'description_ar' => $service->description_ar,
                'description_en' => $service->description_en,
                'image' => $service->attachment?->asset_path,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{name_ar: string, name_en: string, client_name_ar: string|null, client_name_en: string|null, description_ar: string|null, description_en: string|null, project_date: string|null, project_url: string|null, image: string|null}>
     */
    public function getActiveProjects(): Collection
    {
        return Project::query()
            ->with('attachment')
            ->where('is_active', true)
            ->orderBy('ordering')
            ->get()
            ->map(fn (Project $project) => [
                'name_ar' => $project->name_ar,
                'name_en' => $project->name_en,
                'client_name_ar' => $project->client_name_ar,
                'client_name_en' => $project->client_name_en,
                'description_ar' => $project->description_ar,
                'description_en' => $project->description_en,
                'project_date' => $project->project_date?->format('Y-m-d'),
                'project_url' => $project->project_url,
                'image' => $project->attachment?->asset_path,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{name_ar: string, name_en: string, position_ar: string|null, position_en: string|null, bio_ar: string|null, bio_en: string|null, email: string|null, phone: string|null, linkedin_url: string|null, image: string|null}>
     */
    public function getActiveTeamMembers(): Collection
    {
        return TeamMember::query()
            ->with('attachment')
            ->where('is_active', true)
            ->orderBy('ordering')
            ->get()
            ->map(fn (TeamMember $teamMember) => [
                'name_ar' => $teamMember->name_ar,
                'name_en' => $teamMember->name_en,
                'position_ar' => $teamMember->position_ar,
                'position_en' => $teamMember->position_en,
                'bio_ar' => $teamMember->bio_ar,
                'bio_en' => $teamMember->bio_en,
                'email' => $teamMember->email,
                'phone' => $teamMember->phone,
                'linkedin_url' => $teamMember->linkedin_url,
                'image' => $teamMember->attachment?->asset_path,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{name_ar: string, name_en: string, website: string|null, logo: string|null}>
     */
    public function getActiveClients(): Collection
    {
        return ClientPartner::query()
            ->with('attachment')
            ->where('type', ClientPartnerType::Client)
            ->where('is_active', true)
            ->orderBy('ordering')
            ->get()
            ->map(fn (ClientPartner $record) => [
                'name_ar' => $record->name_ar,
                'name_en' => $record->name_en,
                'website' => $record->website,
                'logo' => $record->attachment?->asset_path,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{name_ar: string, name_en: string, website: string|null, logo: string|null}>
     */
    public function getActivePartners(): Collection
    {
        return ClientPartner::query()
            ->with('attachment')
            ->where('type', ClientPartnerType::Partner)
            ->where('is_active', true)
            ->orderBy('ordering')
            ->get()
            ->map(fn (ClientPartner $record) => [
                'name_ar' => $record->name_ar,
                'name_en' => $record->name_en,
                'website' => $record->website,
                'logo' => $record->attachment?->asset_path,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{title_ar: string, title_en: string, issuer_ar: string|null, issuer_en: string|null, description_ar: string|null, description_en: string|null, issued_date: string|null, external_url: string|null, image: string|null}>
     */
    public function getActiveCertificates(): Collection
    {
        return CertificateAward::query()
            ->with('attachment')
            ->where('type', CertificateAwardType::Certificate)
            ->where('is_active', true)
            ->orderBy('ordering')
            ->get()
            ->map(fn (CertificateAward $record) => [
                'title_ar' => $record->title_ar,
                'title_en' => $record->title_en,
                'issuer_ar' => $record->issuer_ar,
                'issuer_en' => $record->issuer_en,
                'description_ar' => $record->description_ar,
                'description_en' => $record->description_en,
                'issued_date' => $record->issued_date?->format('Y-m-d'),
                'external_url' => $record->external_url,
                'image' => $record->attachment?->asset_path,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{title_ar: string, title_en: string, issuer_ar: string|null, issuer_en: string|null, description_ar: string|null, description_en: string|null, issued_date: string|null, external_url: string|null, image: string|null}>
     */
    public function getActiveAwards(): Collection
    {
        return CertificateAward::query()
            ->with('attachment')
            ->where('type', CertificateAwardType::Award)
            ->where('is_active', true)
            ->orderBy('ordering')
            ->get()
            ->map(fn (CertificateAward $record) => [
                'title_ar' => $record->title_ar,
                'title_en' => $record->title_en,
                'issuer_ar' => $record->issuer_ar,
                'issuer_en' => $record->issuer_en,
                'description_ar' => $record->description_ar,
                'description_en' => $record->description_en,
                'issued_date' => $record->issued_date?->format('Y-m-d'),
                'external_url' => $record->external_url,
                'image' => $record->attachment?->asset_path,
            ])
            ->values();
    }
}
