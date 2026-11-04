<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Asset;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class SortAssetsProcessor implements ProcessorInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param Publication $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Publication
    {
        /** @var Request $request */
        $request = $context['request'];

        $order = $request->request->all('order');
        if (empty($order)) {
            throw new BadRequestHttpException('Missing order');
        }

        $this->em->beginTransaction();
        try {
            $dql = sprintf(
                'UPDATE %s a SET a.position = :pos WHERE a.publication = :pubId AND a.id = :assetId',
                Asset::class
            );
            $publicationId = $data->getId();
            foreach ($order as $i => $assetId) {
                $this->em
                    ->createQuery($dql)
                    ->execute([
                        'pos' => $i,
                        'pubId' => $publicationId,
                        'assetId' => $assetId,
                    ]);
            }
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $data;
    }
}
