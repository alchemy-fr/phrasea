<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class PublicationNormalizer extends AbstractRouterNormalizer
{
    private Security $security;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(Security $security, UrlGeneratorInterface $urlGenerator)
    {
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @param Publication $object
     */
    public function normalize($object, array &$context = []): void
    {
        if (in_array(Publication::GROUP_READ, $context['groups'])) {
            $isAuthorized = $this->security->isGranted(PublicationVoter::READ_DETAILS, $object);
            $object->setAuthorized($isAuthorized);
            if (!$isAuthorized) {
                $context['groups'] = [Publication::GROUP_INDEX];
            }

            if ($this->security->isGranted(PublicationVoter::EDIT, $object)) {
                $context['groups'][] = Publication::GROUP_ADMIN_READ;
            }
        }

        $object->setChildren(new ArrayCollection($object->getChildren()->filter(function (Publication $child): bool {
            return $this->security->isGranted(PublicationVoter::READ, $child);
        })->getValues()));

        if ($object->getPackage() instanceof Asset) {
            $object->setPackageUrl($this->generateAssetUrl($object->getPackage()));
        }

        if (!empty($css = $object->getCss())) {
            $object->setCssLink($this->urlGenerator->generate('publication_css', [
                'id' => $object->getId(),
                'hash' => md5($css),
            ], UrlGeneratorInterface::ABSOLUTE_URL));
        }

        $config = $object->getConfig();
        $securityContainer = $object->getSecurityContainer();
        $securityContainerConfig = $securityContainer->getConfig();
        $object->setSecurityContainerId($securityContainer->getId());
        $config->setSecurityMethod($securityContainerConfig->getSecurityMethod());
        $config->setSecurityOptions($securityContainerConfig->getSecurityOptions());
    }

    public function support($object): bool
    {
        return $object instanceof Publication;
    }
}
