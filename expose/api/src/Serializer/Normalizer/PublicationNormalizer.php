<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use Alchemy\AuthBundle\Security\Voter\AbstractVoter;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PublicationNormalizer extends AbstractRouterNormalizer
{
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @param Publication $object
     */
    public function normalize($object, array &$context = []): void
    {
        $context['enable_max_depth'] = true;
        if (in_array(Publication::GROUP_READ, $context['groups'])) {
            $isAuthorized = $this->security->isGranted(PublicationVoter::READ_DETAILS, $object);
            $object->setAuthorized($isAuthorized);
            if (!$isAuthorized) {
                if ($object->isPubliclyListed()) {
                    $context['groups'] = [Publication::GROUP_INDEX];
                } else {
                    $context['groups'] = ['_'];
                }
            }

            if ($this->security->isGranted(AbstractVoter::EDIT, $object)) {
                $context['groups'][] = Publication::GROUP_ADMIN_READ;
            }
        }

        $object->setChildren(new ArrayCollection($object->getChildren()->filter(fn (Publication $child): bool => $this->security->isGranted(PublicationVoter::READ, $child))->getValues()));

        if ($object->getPackage() instanceof Asset && $this->getDownloadMode($object)->exposesFiles()) {
            $object->setPackageUrl($this->generateAssetUrl($object->getPackage()));
        }

        $object->setArchiveDownloadUrl($this->generateArchiveDownloadUrl($object));

        if (!empty($css = $object->getCss())) {
            $object->setCssLink($this->urlGenerator->generate('publication_css', [
                'id' => $object->getId(),
                'hash' => md5($css),
            ], UrlGeneratorInterface::ABSOLUTE_URL));
        }

        $config = $object->getConfig();
        $securityContainer = $object->getSecurityContainer();
        $object->setSecurityContainerId($securityContainer->getId());
        $config->setSecurityMethod($securityContainer->getSecurityMethod());
        $config->setSecurityOptions($securityContainer->getSecurityOptions());

        $object->setCapabilities([
            'edit' => $this->security->isGranted(AbstractVoter::EDIT, $object),
            'delete' => $this->security->isGranted(AbstractVoter::DELETE, $object),
            'operator' => $this->security->isGranted(AbstractVoter::OPERATOR, $object),
        ]);
    }

    public function support($object): bool
    {
        return $object instanceof Publication;
    }
}
