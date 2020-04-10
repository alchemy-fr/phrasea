<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Symfony\Component\Security\Core\Security;

class PublicationNormalizer extends AbstractRouterNormalizer
{
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * @param Publication $object
     */
    public function normalize($object, array &$context = [])
    {
        if (in_array(Publication::GROUP_PUB_READ, $context['groups'])) {
            $isAuthorized = $this->security->isGranted(PublicationVoter::READ_DETAILS, $object);
            $object->setAuthorized($isAuthorized);
            if (!$isAuthorized) {
                $context['groups'] = [Publication::GROUP_PUB_INDEX];
            }

            if ($this->security->isGranted(PublicationVoter::EDIT, $object)) {
                $context['groups'][] = Publication::GROUP_PUB_ADMIN_READ;
            }
        }

        $object->setChildren($object->getChildren()->filter(function (Publication $child): bool {
            return $this->security->isGranted(PublicationVoter::INDEX, $child);
        }));

        if ($object->getPackage() instanceof Asset) {
            $object->setPackageUrl($this->generateAssetUrl($object->getPackage()));
        }
        if ($object->getCover() instanceof Asset) {
            $object->setCoverUrl($this->generateAssetUrl($object->getCover()));
        }

        $securityContainer = $object->getSecurityContainer();
        $object->setSecurityContainerId($securityContainer->getId());
        $object->setSecurityMethod($securityContainer->getSecurityMethod());
        $object->setSecurityOptions($securityContainer->getSecurityOptions());
    }

    public function support($object, $format): bool
    {
        return $object instanceof Publication;
    }
}
