<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetPublicationAction extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
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

        return $publication;
    }
}
