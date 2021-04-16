<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use App\ZippyManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DownloadViaZippyAction extends AbstractController
{
    private EntityManagerInterface $em;
    private ZippyManager $zippyManager;

    public function __construct(EntityManagerInterface $em, ZippyManager $zippyManager)
    {
        $this->em = $em;
        $this->zippyManager = $zippyManager;
    }

    public function __invoke(string $id, Request $request): Response
    {
        /** @var Publication $publication */
        $publication = $this->em->find(Publication::class, $id);
        if (!$publication) {
            throw new NotFoundHttpException();
        }
        $this->denyAccessUnlessGranted(PublicationVoter::READ, $publication);

        $url = $this->zippyManager->getDownloadUrl($publication);

        return new JsonResponse([
            'downloadUrl' => $url,
        ]);
    }
}
