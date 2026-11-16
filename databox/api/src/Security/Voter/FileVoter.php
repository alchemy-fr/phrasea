<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\File;
use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationData;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * A file has no rights of its own: it is granted through what uses it.
 *
 * - the source of an asset: the rights on the asset;
 * - a rendition: the rendition policy (READ on the rendition) on top of the rights on the asset;
 * - a previous source kept as a version (READ only): READ on the asset;
 * - the image (or thumbnail) of an entity list value or of a workspace logo (READ only):
 *   READ on the value or on the workspace;
 * - a terms file of a workspace (READ only): READ on the workspace;
 * - an integration output (READ only): as its data on the input file, READ on the input file
 *   for the user's own output, EDIT for the others.
 *
 * Any other file is never granted here: a message attachment, or the source file of an asset
 * relationship, which may be set without reading the file (granting it would let one read any
 * file by linking it to an asset of one's own).
 */
class FileVoter extends AbstractVoter
{
    /**
     * Name of the integration data whose value is the ID of an output file
     * (see FileUserActionsTrait::DATA_FILE_ID).
     */
    public const string INTEGRATION_OUTPUT_DATA_NAME = 'file_id';

    /**
     * @var array<string, true> files being voted on, an integration output being granted through its input file
     */
    private array $voting = [];

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof File;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, File::class, true);
    }

    /**
     * @param File $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $fileId = $subject->getId();
        if (isset($this->voting[$fileId])) {
            return false;
        }

        $this->voting[$fileId] = true;
        try {
            return $this->doVote($attribute, $subject, $token);
        } finally {
            unset($this->voting[$fileId]);
        }
    }

    private function doVote(string $attribute, File $file, TokenInterface $token): bool
    {
        foreach ($this->findIdsByFile(Asset::class, 'source', $file) as $id) {
            if ($this->security->isGranted($attribute, $this->em->find(Asset::class, $id))) {
                return true;
            }
        }

        foreach ($this->findIdsByFile(AssetRendition::class, 'file', $file) as $id) {
            $rendition = $this->em->find(AssetRendition::class, $id);
            if ($this->security->isGranted(self::READ, $rendition)
                && (self::READ === $attribute || $this->security->isGranted($attribute, $rendition->getAsset()))) {
                return true;
            }
        }

        if (self::READ !== $attribute) {
            return false;
        }

        foreach ($this->findIdsByFile(AssetFileVersion::class, 'file', $file) as $id) {
            if ($this->security->isGranted(self::READ, $this->em->find(AssetFileVersion::class, $id))) {
                return true;
            }
        }

        foreach ($this->findIdsByImageFile(AttributeEntity::class, 'image', $file) as $id) {
            if ($this->security->isGranted(self::READ, $this->em->find(AttributeEntity::class, $id))) {
                return true;
            }
        }

        foreach ($this->findIdsByImageFile(Workspace::class, 'logo', $file) as $id) {
            if ($this->security->isGranted(self::READ, $this->em->find(Workspace::class, $id))) {
                return true;
            }
        }

        foreach ($this->findIdsByFile(TermsVersion::class, 'file', $file, 'IDENTITY(t.workspace)') as $id) {
            if ($this->security->isGranted(self::READ, $this->em->find(Workspace::class, $id))) {
                return true;
            }
        }

        return $this->isReadableIntegrationOutput($file, $token);
    }

    /**
     * @param class-string $entityClass
     * @param string       $select      the ID to return, of the entity by default
     *
     * @return iterable<string>
     */
    private function findIdsByFile(string $entityClass, string $field, File $file, string $select = 't.id'): iterable
    {
        $rows = $this->em->createQueryBuilder()
            ->select($select.' AS id')
            ->from($entityClass, 't')
            ->andWhere(sprintf('t.%s = :f', $field))
            ->setParameter('f', $file->getId())
            ->getQuery()
            ->toIterable();

        foreach ($rows as $row) {
            yield (string) $row['id'];
        }
    }

    /**
     * Owners of an image set (AttributeEntity::$image, Workspace::$logo) holding the file.
     *
     * @param class-string $ownerClass
     *
     * @return iterable<string>
     */
    private function findIdsByImageFile(string $ownerClass, string $imageField, File $file): iterable
    {
        $rows = $this->em->createQueryBuilder()
            ->select('t.id')
            ->from($ownerClass, 't')
            ->innerJoin(sprintf('t.%s', $imageField), 'i')
            ->andWhere('i.imageFile = :f OR i.thumbnailFile = :f')
            ->setParameter('f', $file->getId())
            ->getQuery()
            ->toIterable();

        foreach ($rows as $row) {
            yield (string) $row['id'];
        }
    }

    private function isReadableIntegrationOutput(File $file, TokenInterface $token): bool
    {
        /** @var IntegrationData[] $data */
        $data = $this->em->createQueryBuilder()
            ->select('d')
            ->from(IntegrationData::class, 'd')
            ->innerJoin('d.integration', 'i')
            ->andWhere('d.name = :name')
            ->andWhere('d.value = :id')
            ->andWhere('i.workspace IS NULL OR i.workspace = :ws')
            ->setParameter('name', self::INTEGRATION_OUTPUT_DATA_NAME)
            ->setParameter('id', $file->getId())
            ->setParameter('ws', $file->getWorkspaceId())
            ->getQuery()
            ->getResult();

        $userId = $token->getUser()?->getUserIdentifier();
        foreach ($data as $d) {
            $input = $d->getObject();
            if (!$input instanceof File || $input->getWorkspaceId() !== $file->getWorkspaceId()) {
                continue;
            }

            // Same visibility as the data of a file listed by the integration (WorkspaceIntegrationOutputMapper)
            $ownData = null !== $userId && $d->getUserId() === $userId;
            if ($this->security->isGranted($ownData ? self::READ : self::EDIT, $input)) {
                return true;
            }
        }

        return false;
    }
}
