<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AttributeListItemInput;
use App\Entity\AttributeList\AttributeListItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PutAttributeListItemProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param AttributeListItemInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AttributeListItem
    {
        $listId = $uriVariables['id'];
        $itemId = $uriVariables['itemId'];

        $item = $this->em->getRepository(AttributeListItem::class)->findOneBy([
            'id' => $itemId,
            'list' => $listId,
        ]);
        if (!$item instanceof AttributeListItem) {
            throw new NotFoundHttpException(sprintf('Attribute list item "%s" (list "%s") not found', $itemId, $listId));
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
