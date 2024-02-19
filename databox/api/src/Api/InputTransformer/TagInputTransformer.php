<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\TagInput;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class TagInputTransformer extends AbstractInputTransformer
{
    public function supports(string $resourceClass, object $data): bool
    {
        return Tag::class === $resourceClass && $data instanceof TagInput;
    }

    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new Tag();
        $object->setName($data->name);

        if ($isNew) {
            if (!$data->workspace instanceof Workspace) {
                throw new BadRequestHttpException('Missing workspace');
            }

            if ($data->name) {
                $tag = $this->em->getRepository(Tag::class)->findOneBy([
                    'name' => $data->name,
                    'workspace' => $data->workspace->getId()
                ]);

                if ($tag) {
                    $isNew = false;
                    $object = $tag;
                }
            }
        }

        if ($isNew) {
            $object->setWorkspace($data->workspace);
        }

        return $object;
    }
}
