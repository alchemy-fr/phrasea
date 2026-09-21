<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Asset;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Persists a new asset, turning a race on the (workspace, key) unique
 * constraint into a 409 instead of a 500.
 *
 * AssetInputTransformer already reuses the existing asset when the key is
 * known, but two concurrent POSTs with the same key both pass that lookup
 * before either flush.
 */
final readonly class CreateAssetProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $decorated,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        try {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        } catch (UniqueConstraintViolationException $e) {
            if ($data instanceof Asset && null !== $data->getKey() && str_contains($e->getMessage(), 'uniq_ws_key')) {
                throw new ConflictHttpException(sprintf('An asset with key "%s" already exists in this workspace', $data->getKey()), $e);
            }

            throw $e;
        }
    }
}
