<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Entity\Core\Collection;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MoveCollectionAction extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function __invoke(Collection $data, string $dest, Request $request): Collection
    {
        $this->denyAccessUnlessGranted(CollectionVoter::EDIT, $data);

        $isRoot = 'root' === $dest;
        $this->denyAccessUnlessGranted(CollectionVoter::EDIT, $data);

        if ($isRoot) {
            $destination = null;
        } else {
            $destination = $this->em->find(Collection::class, $dest);
            if (!$destination instanceof Collection) {
                throw new NotFoundHttpException(sprintf('Collection destination "%s" not found', $dest));
            }
            $this->denyAccessUnlessGranted(CollectionVoter::EDIT, $destination);
        }

        $data->setParent($destination);

        return $data;
    }
}
