<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetPublicationAction extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function __invoke(string $id): Publication
    {
        $params = Uuid::isValid($id) ? ['id' => $id] : ['slug' => $id];
        /** @var Publication|null $publication */
        $publication = $this->em
            ->getRepository(Publication::class)
            ->findOneBy($params);

        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException();
        }

        if (!$this->isGranted(PublicationVoter::READ, $publication)) {
            throw new NotFoundHttpException();
        }

        return $publication;
    }
}
