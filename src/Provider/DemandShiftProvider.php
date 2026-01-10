<?php

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\DemandShiftRepository;
use Symfony\Bundle\SecurityBundle\Security;
use App\Service\PartnerService;

class DemandShiftProvider implements ProviderInterface
{
    public function __construct(
        private readonly DemandShiftRepository $demandShiftRepository,
        private readonly Security $security,
        private readonly PartnerService $partnerService
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user) {
            return [];
        }

        // Admin sieht alles
        if ($this->security->isGranted('ROLE_ADMIN')) {
            // GetCollection
            if (empty($uriVariables)) {
                return $this->demandShiftRepository->findAll();
            }
            // Get single
            return $this->demandShiftRepository->find($uriVariables['id']);
        }

        // Partner Admin sieht nur zugewiesene
        if ($this->security->isGranted('ROLE_PARTNER_ADMIN')) {
            $partner = $this->partnerService->getLoggedInPartner();

            if (!$partner) {
                return [];
            }

            $qb = $this->demandShiftRepository->createQueryBuilder('ds')
                ->leftJoin('ds.partnerVisibility', 'pv')
                ->where('pv = :partner')
                ->setParameter('partner', $partner);

            // Get single
            if (!empty($uriVariables)) {
                $qb->andWhere('ds.id = :id')
                    ->setParameter('id', $uriVariables['id']);

                return $qb->getQuery()->getOneOrNullResult();
            }

            // GetCollection
            return $qb->getQuery()->getResult();
        }

        return [];
    }
}
