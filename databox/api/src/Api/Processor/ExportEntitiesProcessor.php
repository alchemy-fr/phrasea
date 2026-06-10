<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ExportEntitiesInput;
use App\Attribute\AttributeInterface;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\Attribute\AttributeEntity\Exporter\AttributeEntityExporterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\ServiceProviderInterface;

class ExportEntitiesProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeEntityRepository $attributeEntityRepository,
        #[TaggedLocator(AttributeEntityExporterInterface::TAG, defaultIndexMethod: 'getName')]
        private readonly ServiceProviderInterface $exporters,
    ) {
    }

    /**
     * @param ExportEntitiesInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        if (AttributeInterface::NO_LOCALE === $data->locale) {
            $data->locale = null;
        }

        $listId = $uriVariables['id'];
        $list = DoctrineUtil::findStrict($this->em, EntityList::class, $listId);
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $list);

        try {
            /** @var AttributeEntityExporterInterface $importer */
            $importer = $this->exporters->get($data->format);
        } catch (NotFoundExceptionInterface $e) {
            throw new BadRequestHttpException(sprintf('Unsupported export format "%s".', $data->format), previous: $e);
        }

        return new StreamedResponse($importer->export($list, $data), Response::HTTP_OK, [
            'Content-Type' => $importer->getMimeType($data),
            'Content-Disposition' => sprintf('attachment; filename="%s"', $importer->getFilename($list, $data)),
        ]);
    }
}
