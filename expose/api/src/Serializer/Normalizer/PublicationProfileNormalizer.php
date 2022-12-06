<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\PublicationProfile;
use App\Security\Voter\PublicationProfileVoter;
use Symfony\Component\Security\Core\Security;

class PublicationProfileNormalizer extends AbstractRouterNormalizer
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * @param PublicationProfile $object
     */
    public function normalize($object, array &$context = []): void
    {
        if (in_array(PublicationProfile::GROUP_READ, $context['groups'])) {
            if ($this->security->isGranted(PublicationProfileVoter::EDIT, $object)) {
                $context['groups'][] = PublicationProfile::GROUP_ADMIN_READ;
            }
        }

        $object->setCapabilities([
            'edit' => $this->security->isGranted(PublicationProfileVoter::EDIT, $object),
            'delete' => $this->security->isGranted(PublicationProfileVoter::DELETE, $object),
        ]);
    }

    public function support($object): bool
    {
        return $object instanceof PublicationProfile;
    }
}
