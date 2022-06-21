<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\AttributeClassInput;
use App\Entity\Core\AttributeClass;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AttributeClassInputDataTransformer extends AbstractInputDataTransformer
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param AttributeClassInput $data
     */
    public function transform($data, string $to, array $context = [])
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
