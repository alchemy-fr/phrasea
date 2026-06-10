<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ImportEntitiesInput;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AttributeEntity\Importer\AttributeEntityImporterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

class ImportEntitiesProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeEntityRepository $attributeEntityRepository,
        #[TaggedLocator(AttributeEntityImporterInterface::TAG, defaultIndexMethod: 'getName')]
        private readonly ServiceProviderInterface $importers,
    ) {
    }

    /**
     * @param ImportEntitiesInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): EntityList
    {
        $listId = $uriVariables['id'];
        $list = DoctrineUtil::findStrict($this->em, EntityList::class, $listId);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        if (empty($data->data)) {
            return $list;
        }

        /** @var AttributeEntityImporterInterface $importer */
        $importer = $this->importers->get($data->format);
        $importer->import($list, $data->data);

        return $list;
    }
}
