<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\TagInput;
use App\Entity\Core\Tag;
use App\Entity\Core\Workspace;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsTaggedItem(index: TagInput::class)]
class TagInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    /**
     * @param TagInput $data
     */
    public function map(object $data, ?object $target, array $context = []): Tag
    {
        $isNew = null === $target;
        $object = $target ?? new Tag();

        if ($isNew) {
            if (!$data->workspace instanceof Workspace) {
                throw new BadRequestHttpException('Missing workspace');
            }

            if ($data->name) {
                $tag = $this->em->getRepository(Tag::class)->findOneBy([
                    'name' => $data->name,
                    'workspace' => $data->workspace->getId(),
                ]);

                if ($tag instanceof Tag) {
                    $isNew = false;
                    $object = $tag;
                }
            }
        }

        if ($isNew) {
            $object->setWorkspace($data->workspace);
        }

        if (null !== $data->name) {
            $object->setName($data->name);
        }
        if (null !== $data->color) {
            $object->setColor($data->color);
        }
        if (null !== $data->translations) {
            $object->setTranslations($data->translations);
        }

        return $object;
    }
}
