<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\AttributePolicyInput;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AttributePolicyInputTransformer extends AbstractInputTransformer
{
    public function supports(string $resourceClass, object $data): bool
    {
        return AttributePolicy::class === $resourceClass && $data instanceof AttributePolicyInput;
    }

    /**
     * @param AttributePolicyInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        /** @var AttributePolicy $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new AttributePolicy();

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
