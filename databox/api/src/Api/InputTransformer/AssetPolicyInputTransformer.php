<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use ApiPlatform\Metadata\Operation;
use App\Api\EntityIriConverter;
use App\Api\Model\Input\AssetPolicyInput;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\AssetPolicy\AssetPolicyUser;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AssetPolicyInputTransformer extends AbstractInputTransformer
{
    public function __construct(
        private readonly EntityIriConverter $iriConverter,
    ) {
    }

    public function supports(string $resourceClass, object $data): bool
    {
        return AssetPolicy::class === $resourceClass && $data instanceof AssetPolicyInput;
    }

    /**
     * @param AssetPolicyInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        $entity = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new AssetPolicy();
        /** @var Operation $operation */
        $operation = $context['operation'];
        $this->validator->validate($data, $operation->getValidationContext() ?? []);

        if ($isNew) {
            $user = $this->getStrictUser();
            $entity->setOwnerId($user->getUserIdentifier());
        }

        if ($data->workspace) {
            /** @var Workspace $workspace */
            $workspace = $this->iriConverter->getItemFromIri(Workspace::class, $data->workspace);
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $workspace);
            $entity->setWorkspace($workspace);
        } elseif ($isNew) {
            throw new BadRequestHttpException('Missing workspace');
        }

        if (null !== $data->groups || null !== $data->users) {
            $entity->getUsers()->clear();

            if (!empty($data->groups)) {
                foreach ($data->groups as $group) {
                    $u = new AssetPolicyUser();
                    $u->setPolicy($entity);
                    $u->setUserType(AssetPolicyUser::TYPE_GROUP);
                    $u->setUserId($group);
                    $entity->getUsers()->add($u);
                }
            }

            if (!empty($data->users)) {
                foreach ($data->users as $user) {
                    $u = new AssetPolicyUser();
                    $u->setPolicy($entity);
                    $u->setUserType(AssetPolicyUser::TYPE_USER);
                    $u->setUserId($user);
                    $entity->getUsers()->add($u);
                }
            }
        }

        if (null !== $data->name) {
            $entity->setName($data->name);
        }
        if (null !== $data->conditions) {
            $entity->setConditions($data->conditions);
        }
        if (null !== $data->actions) {
            $entity->setActions($data->actions);
        }
        if (null !== $data->enabled) {
            $entity->setEnabled($data->enabled);
        }

        return $entity;
    }
}
