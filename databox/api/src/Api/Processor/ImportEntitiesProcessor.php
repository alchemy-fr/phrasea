<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ImportEntitiesInput;
use App\Entity\Core\EntityList;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AttributeEntity\Importer\AttributeEntityImporterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\ServiceProviderInterface;

class ImportEntitiesProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
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

        try {
            /** @var AttributeEntityImporterInterface $importer */
            $importer = $this->importers->get($data->format);
        } catch (NotFoundExceptionInterface $e) {
            throw new BadRequestHttpException(sprintf('Unsupported import format "%s".', $data->format), previous: $e);
        }

        $importer->import($list, $data->data);

        return $list;
    }
}
