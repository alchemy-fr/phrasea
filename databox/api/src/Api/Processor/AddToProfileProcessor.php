<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AddToProfileInput;
use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileItem;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Profile\ProfileRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AddToProfileProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly IriConverterInterface $iriConverter,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param AddToProfileInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Profile
    {
        $user = $this->getStrictUser();
        if (isset($uriVariables['id'])) {
            $profileId = $uriVariables['id'];
            $profile = DoctrineUtil::findStrictByRepo($this->profileRepository, $profileId);
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $profile);
        } else {
            $profile = $this->profileRepository->findOneBy([
                'ownerId' => $user->getId(),
            ], [
                'createdAt' => 'ASC',
            ]);
        }

        if (null === $profile) {
            $profile = new Profile();
            $profile->setOwnerId($user->getId());
            $this->em->persist($profile);
            $position = 0;
        } else {
            $position = $this->profileRepository->getMaxPosition($profile->getId()) + 1;
        }

        foreach ($data->items as $i) {
            $item = new ProfileItem();
            $item->setDisplayEmpty(true);
            $item->setProfile($profile);
            $item->setType($i->type);
            $item->setPosition($position++);

            switch ($i->type) {
                case ProfileItem::TYPE_ATTR_DEF:
                    $definition = DoctrineUtil::findStrictByRepo($this->attributeDefinitionRepository, $i->definition);
                    $this->denyAccessUnlessGranted(AbstractVoter::READ, $definition);
                    $item->setDefinition($definition);
                    if ($this->profileRepository->hasDefinition($profile->getId(), $definition->getId())) {
                        continue 2;
                    }
                    break;
                case ProfileItem::TYPE_DIVIDER:
                case ProfileItem::TYPE_BUILT_IN:
                    $item->setKey($i->key);
                    break;
                case ProfileItem::TYPE_SPACER:
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unsupported type "%d"', $i->type));
            }
            $this->em->persist($item);
        }

        $this->em->flush();

        return $profile;
    }
}
