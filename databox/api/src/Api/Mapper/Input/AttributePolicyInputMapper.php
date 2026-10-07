<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\AttributePolicyInput;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\Workspace;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsTaggedItem(index: AttributePolicyInput::class)]
class AttributePolicyInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    /**
     * @param AttributePolicyInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        $isNew = null === $target;
        /** @var AttributePolicy $object */
        $object = $target ?? new AttributePolicy();

        $workspace = null;
        if ($data->workspace) {
            $workspace = $data->workspace;
        }

        if ($isNew) {
            if (!$workspace instanceof Workspace) {
                throw new BadRequestHttpException('Missing workspace');
            }

            if ($data->key) {
                $attrClass = $this->em->getRepository(AttributePolicy::class)
                    ->findOneBy([
                        'workspace' => $workspace->getId(),
                        'key' => $data->key,
                    ]);

                if ($attrClass) {
                    $isNew = false;
                    $object = $attrClass;
                }
            }
        }

        if ($isNew) {
            $object->setWorkspace($workspace);
            $object->setKey($data->key);
        }

        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->editable) {
            $object->setEditable($data->editable);
        }
        if (null !== $data->public) {
            $object->setPublic($data->public);
        }
        if (null !== $data->labels) {
            $object->setLabels($data->labels);
        }

        return $object;
    }
}
