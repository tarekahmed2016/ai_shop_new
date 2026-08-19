<?php

namespace App\Http\Controllers;

use App\Services\PublicHomeService;
use Inertia\Inertia;
use Inertia\Response;

class PublicHomeController extends Controller
{
    public function __construct(public PublicHomeService $publicHomeService) {}

    public function __invoke(): Response
    {
        return Inertia::render('Public/HomePage', [
            'companyInfo' => $this->publicHomeService->getPublicCompanyInfo(),
            'heroSlides' => $this->publicHomeService->getActiveHeroSlides(),
            'featureBand' => $this->publicHomeService->getActiveFeatureBand(),
            'promoStrips' => $this->publicHomeService->getActivePromoStrips(),
            'businessCta' => $this->publicHomeService->getActiveBusinessCta(),
            'services' => $this->publicHomeService->getActiveServices(),
            'projects' => $this->publicHomeService->getActiveProjects(),
            'teamMembers' => $this->publicHomeService->getActiveTeamMembers(),
            'clients' => $this->publicHomeService->getActiveClients(),
            'partners' => $this->publicHomeService->getActivePartners(),
            'certificates' => $this->publicHomeService->getActiveCertificates(),
            'awards' => $this->publicHomeService->getActiveAwards(),
        ]);
    }
}
