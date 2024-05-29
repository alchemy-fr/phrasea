<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Basket\Basket;
use App\Integration\IntegrationManager;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BasketActionIntegrationController extends AbstractController
{
    #[Route(path: '/integrations/{integrationId}/baskets/{basketId}/actions/{action}', name: 'integration_basket_action', methods: ['POST'])]
    public function basketAction(
        string $integrationId,
        string $basketId,
        string $action,
        Request $request,
        IntegrationManager $integrationManager,
        EntityManagerInterface $em
    ): Response {
        $wsIntegration = $integrationManager->loadIntegration($integrationId);
        $basket = DoctrineUtil::findStrict($em, Basket::class, $basketId, throw404: true);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $basket, sprintf('Not allowed to edit basket "%s"', $basketId));

        return $integrationManager->handleBasketAction($wsIntegration, $action, $request, $basket);
    }
}
