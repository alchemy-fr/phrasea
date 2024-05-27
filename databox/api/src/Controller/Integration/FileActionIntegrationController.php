<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\File;
use App\Integration\IntegrationManager;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FileActionIntegrationController extends AbstractController
{
    #[Route(path: '/integrations/{integrationId}/files/{fileId}/actions/{action}', name: 'integration_file_action', methods: ['POST'])]
    public function fileAction(
        string $integrationId,
        string $fileId,
        string $action,
        Request $request,
        IntegrationManager $integrationManager,
        EntityManagerInterface $em
    ): Response {
        $wsIntegration = $integrationManager->loadIntegration($integrationId);
        $file = DoctrineUtil::findStrict($em, File::class, $fileId, throw404: true);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $file, sprintf('Not allowed to edit file "%s"', $fileId));

        return $integrationManager->handleFileAction($wsIntegration, $action, $request, $file);
    }
}
