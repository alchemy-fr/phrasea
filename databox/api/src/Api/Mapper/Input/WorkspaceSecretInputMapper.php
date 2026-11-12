<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\WorkspaceSecretInput;
use App\Entity\Integration\WorkspaceSecret;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsTaggedItem(index: WorkspaceSecretInput::class)]
class WorkspaceSecretInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    /**
     * @param WorkspaceSecretInput $data
     */
    public function map(object $data, ?object $target, array $context = []): WorkspaceSecret
    {
        if (null === $target) {
            if (null === $data->value || '' === $data->value) {
                throw new BadRequestHttpException('Missing "value"');
            }

            $object = new WorkspaceSecret();
            $object->setWorkspace($data->workspace);
            $object->setName($data->name);
        } else {
            /** @var WorkspaceSecret $object */
            $object = $target;
            if (null !== $data->name && $data->name !== $object->getName()) {
                throw new BadRequestHttpException('The name of a secret cannot be changed');
            }
        }

        if (null !== $data->value && '' !== $data->value) {
            // Encrypted on flush (SecretListener)
            $object->setPlainValue($data->value);
        }

        return $object;
    }
}
