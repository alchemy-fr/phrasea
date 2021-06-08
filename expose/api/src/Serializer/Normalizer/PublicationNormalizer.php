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
    private bool $zippyEnabled;

    public function __construct(Security $security, ?string $zippyBaseUrl)
    {
        $this->security = $security;
        $this->zippyEnabled = !empty($zippyBaseUrl);
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

        if ($object->isDownloadViaEmail()) {
            $context['download_via_email'] = true;
        }

        $object->setChildren(new ArrayCollection($object->getChildren()->filter(function (Publication $child): bool {
            return $this->security->isGranted(PublicationVoter::READ, $child);
        })->getValues()));

        if ($object->getPackage() instanceof Asset) {
            $object->setPackageUrl($this->generateAssetUrl($object->getPackage()));
        }

        $object->setArchiveDownloadUrl($this->generateDownloadViaZippyUrl($object));

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
    }

    protected function generateDownloadViaZippyUrl(Publication $publication): string
    {
        $uri = $this->urlGenerator->generate(!$publication->isDownloadViaEmail() ? 'archive_download' : 'download_zippy_request_create', [
            'id' => $publication->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->JWTManager->signUri($uri);
    }

    public function support($object): bool
    {
        return $object instanceof Publication;
    }
}
