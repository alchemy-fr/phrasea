<?php

declare(strict_types=1);

namespace App\OperationTask\Task;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Admin\OperationTask;
use App\Entity\Core\EntityList;
use App\OperationTask\OperationTaskInterface;
use App\OperationTask\RunContext;
use App\Service\Asset\Attribute\AttributeEntity\Importer\AttributeEntityImporterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Imports entities into a list in the background: a large import outlasts an HTTP request.
 *
 * The data is dropped from the payload once imported, so that it does not stay in the task.
 */
#[AsTaggedItem(index: self::NAME)]
final readonly class ImportEntitiesTask implements OperationTaskInterface
{
    public const string NAME = 'import_entities';

    public function __construct(
        private EntityManagerInterface $em,
        #[AutowireLocator(AttributeEntityImporterInterface::TAG)]
        private ServiceProviderInterface $importers,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function validate(array $payload): void
    {
        foreach (['listId', 'format', 'data'] as $key) {
            if (empty($payload[$key] ?? null)) {
                throw new BadRequestHttpException(sprintf('%s is required', $key));
            }
        }
        if (!$this->importers->has($payload['format'])) {
            throw new BadRequestHttpException(sprintf('Unsupported import format "%s".', $payload['format']));
        }
    }

    public function handle(array $payload, RunContext $context): void
    {
        try {
            $list = DoctrineUtil::findStrict($this->em, EntityList::class, $payload['listId']);
            /** @var AttributeEntityImporterInterface $importer */
            $importer = $this->importers->get($payload['format']);
            $importer->import($list, $payload['data'], $payload['defaultLocale'] ?? null);
        } finally {
            $this->em->clear();
            $task = $this->em->find(OperationTask::class, $context->getTaskId());
            if (null !== $task) {
                $payload = $task->getPayload();
                unset($payload['data']);
                $task->setPayload($payload);
                $this->em->flush();
            }
        }
    }
}
