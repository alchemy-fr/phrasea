<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\ProfileInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Profile\Profile;
use App\Security\Voter\ProfileVoter;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[AsTaggedItem(index: ProfileInput::class)]
class ProfileInputMapper extends AbstractFileInputMapper implements InputMapperInterface
{
    use WithOwnerIdProcessorTrait;

    /**
     * @param ProfileInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        /** @var Profile $object */
        $object = $target ?? new Profile();

        // Keeping a profile public needs no capability (e.g. renaming it)
        $published = true === $data->public && !$object->isPublic();
        if (null !== $data->public) {
            $object->setPublic($data->public);
        }

        if (null !== $data->name) {
            $object->setName($data->name);
        }

        if (null !== $data->data) {
            $object->assignData($data->data);
        }

        if (null !== $data->description) {
            $object->setDescription($data->description);
        }

        $this->processOwnerId($object);

        if ($published && !$this->isGranted(ProfileVoter::SHARE, $object)) {
            throw new AccessDeniedHttpException('You are not allowed to share this profile.');
        }

        return $object;
    }
}
