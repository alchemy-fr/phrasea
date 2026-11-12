<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\WorkspaceEnvInput;
use App\Entity\Integration\WorkspaceEnv;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsTaggedItem(index: WorkspaceEnvInput::class)]
class WorkspaceEnvInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    /**
     * @param WorkspaceEnvInput $data
     */
    public function map(object $data, ?object $target, array $context = []): WorkspaceEnv
    {
        if (null === $target) {
            if (null === $data->value) {
                throw new BadRequestHttpException('Missing "value"');
            }

            $object = new WorkspaceEnv();
            $object->setWorkspace($data->workspace);
            $object->setName($data->name);
        } else {
            /** @var WorkspaceEnv $object */
            $object = $target;
            if (null !== $data->name && $data->name !== $object->getName()) {
                throw new BadRequestHttpException('The name of a variable cannot be changed');
            }
        }

        if (null !== $data->value) {
            $object->setValue($data->value);
        }

        return $object;
    }
}
