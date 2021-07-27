<?php

declare(strict_types=1);

namespace App\DataPersister;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use App\Entity\Asset;
use App\Entity\MultipartUpload;
use App\Entity\Publication;
use App\Entity\PublicationAsset;
use App\Entity\PublicationProfile;
use App\Security\Voter\PublicationVoter;
use App\Storage\FileStorageManager;
use App\Upload\UploadManager;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Security;

class ExposeDataPersister implements ContextAwareDataPersisterInterface
{
    private DataPersisterInterface $decorated;
    private EntityManagerInterface $em;
    private Security $security;
    private FileStorageManager $storageManager;
    private UploadManager $uploadManager;

    public function __construct(
        DataPersisterInterface $decorated,
        EntityManagerInterface $em,
        Security $security,
        FileStorageManager $storageManager,
        UploadManager $uploadManager
    ) {
        $this->decorated = $decorated;
        $this->em = $em;
        $this->security = $security;
        $this->storageManager = $storageManager;
        $this->uploadManager = $uploadManager;
    }

    public function supports($data, array $context = []): bool
    {
        return $this->decorated->supports($data, $context);
    }

    public function persist($data, array $context = [])
    {
        if ($data instanceof Publication) {
            if ($data->getParentId()) {
                $parent = $this->em->find(Publication::class, $data->getParentId());
                if (!$parent instanceof Publication) {
                    throw new InvalidArgumentException(sprintf('Parent publication %s not found', $data->getParentId()));
                }

                $parent->addChild($data);
                $this->em->persist($parent);
            }
        }

        if ($data instanceof Publication
            || $data instanceof Asset
            || $data instanceof PublicationProfile
        ) {
            $user = $this->security->getUser();
            if ($user instanceof RemoteUser && !$data->getOwnerId()) {
                $data->setOwnerId($user->getId());
            }
        }

        if ($data instanceof PublicationAsset) {
            if (
                !$this->security->isGranted(PublicationVoter::EDIT, $data->getPublication())
                && !$this->security->isGranted(PublicationVoter::CREATE, $data->getPublication())
            ) {
                throw new AccessDeniedHttpException('Cannot edit this publication');
            }
            if (!$this->security->isGranted(PublicationVoter::READ, $data->getAsset())) {
                throw new AccessDeniedHttpException('Cannot edit this asset');
            }
        }

        if ($data instanceof MultipartUpload) {
            $extension = pathinfo($data->getFilename(), PATHINFO_EXTENSION);
            $path = $this->storageManager->generatePath($extension);

            $uploadData = $this->uploadManager->prepareMultipartUpload($path, $data->getType());
            $data->setUploadId($uploadData->get('UploadId'));
            $data->setPath($path);
        }

        $this->decorated->persist($data);

        return $data;
    }

    public function remove($data, array $context = [])
    {
        if ($data instanceof MultipartUpload) {
            $this->uploadManager->cancelMultipartUpload($data->getPath(), $data->getUploadId());
        }

        $this->decorated->remove($data, $context);
    }
}
