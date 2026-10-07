<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AssetInput;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Persists a new asset, turning a race on the (workspace, key) unique
 * constraint into a 409 instead of a 500.
 *
 * AssetInputMapper already reuses the existing asset when the key is
 * known, but two concurrent POSTs with the same key both pass that lookup
 * before either flush.
 */
final readonly class CreateAssetProcessor implements ProcessorInterface
{
    public function __construct(
        private InputMapperProcessor $decorated,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        try {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        } catch (UniqueConstraintViolationException $e) {
            if ($data instanceof AssetInput && null !== $data->key && str_contains($e->getMessage(), 'uniq_ws_key')) {
                throw new ConflictHttpException(sprintf('An asset with key "%s" already exists in this workspace', $data->key), $e);
            }

            throw $e;
        }
    }
}
