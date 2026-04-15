<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ProfileItemInput;
use App\Entity\Profile\ProfileItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PutProfileItemProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param ProfileItemInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ProfileItem
    {
        $profileId = $uriVariables['id'];
        $itemId = $uriVariables['itemId'];

        $item = $this->em->getRepository(ProfileItem::class)->findOneBy([
            'id' => $itemId,
            'profile' => $profileId,
        ]);
        if (!$item instanceof ProfileItem) {
            throw new NotFoundHttpException(sprintf('Profile item "%s" (profile "%s") not found', $itemId, $profileId));
        }

        if (null !== $data->key) {
            $item->setKey($data->key);
        }
        if (null !== $data->displayEmpty) {
            $item->setDisplayEmpty($data->displayEmpty);
        }
        if (null !== $data->format) {
            $item->setFormat($data->format);
        }

        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }
}
