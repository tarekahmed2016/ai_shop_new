<?php

namespace App\Services;

use App\Enums\CertificateAwardType;
use App\Enums\ClientPartnerType;
use App\Models\CertificateAward;
use App\Models\ClientPartner;
use App\Models\Project;
use App\Models\TeamMember;

class PublicNavService
{
    public function __construct(public PublicHomeService $publicHomeService) {}

    /**
     * @return array<string, bool>
     */
    public function getContext(): array
    {
        return [
            'hasFeatureBand' => $this->publicHomeService->getActiveFeatureBand() !== null,
            'hasProjects' => Project::query()->where('is_active', true)->exists(),
            'hasTeamMembers' => TeamMember::query()->where('is_active', true)->exists(),
            'hasClients' => ClientPartner::query()
                ->where('type', ClientPartnerType::Client)
                ->where('is_active', true)
                ->exists(),
            'hasPartners' => ClientPartner::query()
                ->where('type', ClientPartnerType::Partner)
                ->where('is_active', true)
                ->exists(),
            'hasCertificates' => CertificateAward::query()
                ->where('type', CertificateAwardType::Certificate)
                ->where('is_active', true)
                ->exists(),
            'hasAwards' => CertificateAward::query()
                ->where('type', CertificateAwardType::Award)
                ->where('is_active', true)
                ->exists(),
        ];
    }
}
