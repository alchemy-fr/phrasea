<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Security;
use Throwable;

final class SortAssetsAction extends AbstractController
{
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function __invoke(string $id, Request $request)
    {
        /** @var Publication $publication */
        $publication = $this->em->find(Publication::class, $id);
        if (!$publication) {
            throw new NotFoundHttpException();
        }
        $this->denyAccessUnlessGranted(PublicationVoter::EDIT, $publication);

        $order = $request->request->get('order', []);
        if (empty($order)) {
            throw new BadRequestHttpException('Missing order');
        }
        $this->em->beginTransaction();
        try {
            $dql = sprintf(
                'UPDATE %s pa SET pa.position = :pos WHERE pa.publication = :pubId AND pa.asset = :assetId',
                PublicationAsset::class
            );
            foreach ($order as $i => $id) {
                $this->em
                    ->createQuery($dql)
                    ->execute([
                        'pos' => $i,
                        'pubId' => $publication->getId(),
                        'assetId' => $id,
                    ]);
            }
            $this->em->commit();
        } catch (Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $publication;
    }
}
