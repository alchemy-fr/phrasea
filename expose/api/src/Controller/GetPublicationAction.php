<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Entity\Publication;
use App\Report\ExposeLogActionInterface;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetPublicationAction extends AbstractController
{
    private EntityManagerInterface $em;
    private ReportUserService $reportClient;

    public function __construct(EntityManagerInterface $em, ReportUserService $reportClient)
    {
        $this->em = $em;
        $this->reportClient = $reportClient;
    }

    public function __invoke(string $id, Request $request): Publication
    {
        $params = Uuid::isValid($id) ? ['id' => $id] : ['slug' => $id];
        /** @var Publication|null $publication */
        $publication = $this->em
            ->getRepository(Publication::class)
            ->findOneBy($params);

        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(PublicationVoter::READ, $publication);

        $this->reportClient->pushHttpRequestLog(
            $request,
            ExposeLogActionInterface::PUBLICATION_VIEW,
            $publication->getId()
        );

        return $publication;
    }
}
