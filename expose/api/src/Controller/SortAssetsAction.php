<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Security\Voter\PublicationVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SortAssetsAction extends AbstractAssetAction
{
    public function __invoke(string $id, Request $request)
    {
        $publication = $this->getPublication($id, PublicationVoter::EDIT);

        $order = $request->request->get('order', []);
        if (empty($order)) {
            throw new BadRequestHttpException('Missing order');
        }
        $this->em->beginTransaction();
        try {
            $dql = sprintf(
                'UPDATE %s a SET a.position = :pos WHERE a.publication = :pubId AND a.id = :assetId',
                Asset::class
            );
            $publicationId = $publication->getId();
            foreach ($order as $i => $id) {
                $this->em
                    ->createQuery($dql)
                    ->execute([
                        'pos' => $i,
                        'pubId' => $publicationId,
                        'assetId' => $id,
                    ]);
            }
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $publication;
    }
}
