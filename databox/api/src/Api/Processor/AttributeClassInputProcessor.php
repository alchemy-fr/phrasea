<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Metadata\Operation;
use App\Api\Model\Input\AttributeClassInput;
use App\Entity\Core\AttributeClass;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AttributeClassInputProcessor extends AbstractInputProcessor
{
    /**
     * @param AttributeClassInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var AttributeClass $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new AttributeClass();

        $workspace = null;
        if ($data->workspace) {
            $workspace = $data->workspace;
        }

        if ($isNew) {
            if (!$workspace instanceof Workspace) {
                throw new BadRequestHttpException('Missing workspace');
            }

            if ($data->key) {
                $attrClass = $this->em->getRepository(AttributeClass::class)
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

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof AttributeClass) {
            return false;
        }

        return AttributeClass::class === $to && AttributeClassInput::class === ($context['input']['class'] ?? null);
    }
}