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
            $isAuthorized = $this->isAuthorized($object);
            $object->setAuthorized($isAuthorized);
            if (!$isAuthorized) {
                $context['groups'] = [Publication::GROUP_PUB_INDEX];
            }
        }

        if ($object->getPackage() instanceof Asset) {
            $object->setPackageUrl($this->generateAssetUrl('asset_download', $object->getPackage()));
        }
        if ($object->getCover() instanceof Asset) {
            $object->setCoverUrl($this->generateAssetUrl('asset_thumbnail', $object->getCover()));
        }
    }

    private function isAuthorized(Publication $publication): bool
    {
        return $this->security->isGranted(PublicationVoter::READ, $publication);
    }

    public function support($object, $format): bool
    {
        return $object instanceof Publication;
    }
}
