<?php

namespace App\Twig;

use App\Entity\PartnerCompany;
use App\Service\PartnerService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GlobalExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly PartnerService $partnerService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_admin', [$this, 'isAdmin']),
            new TwigFunction('is_partner_admin', [$this, 'isPartnerAdmin']),
            new TwigFunction('current_partner', [$this, 'getCurrentPartner']),
        ];
    }

    public function isAdmin(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    public function isPartnerAdmin(): bool
    {
        return $this->security->isGranted('ROLE_PARTNER_ADMIN');
    }

    public function getCurrentPartner(): ?PartnerCompany
    {
        return $this->partnerService->getLoggedInPartner();
    }
}
