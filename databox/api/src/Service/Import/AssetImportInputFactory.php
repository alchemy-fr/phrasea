<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Api\Model\Input\AssetInput;
use App\Api\Model\Input\AssetRenditionInput;
use App\Api\Model\Input\Attribute\AttributeInput;
use App\Api\Model\Input\FileSourceInput;
use App\Entity\Core\AssetImport;
use App\Entity\Core\Collection;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Tag;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Service\Import\Exception\InvalidImportEntryException;
use App\Service\Storage\RenditionManager;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Builds the input of POST /assets from a normalized entry (see ImportEntryNormalizer):
 * files are always imported from their URL, and the given renditions are substituted
 * so that the ingest workflow does not build them again.
 */
final readonly class AssetImportInputFactory
{
    public function __construct(
        private EntityManagerInterface $em,
        private RenditionManager $renditionManager,
    ) {
    }

    /**
     * @param string|null $pathCollectionId the collection resolved from the "path" of the entry
     *
     * @throws InvalidImportEntryException
     */
    public function create(AssetImport $import, array $entry, ?string $pathCollectionId, string $ownerId): AssetInput
    {
        $workspace = $import->getWorkspace();
        $workspaceId = $workspace->getId();

        $input = new AssetInput();
        $input->workspace = $workspace;
        $input->ownerId = $ownerId;
        $input->collection = $this->resolveCollection($import, $entry['collection'] ?? null, $pathCollectionId);
        $input->key = $entry['key'] ?? null;
        $input->externalId = $entry['externalId'] ?? null;
        $input->name = $entry['name'] ?? null;

        $privacy = $entry['privacy'] ?? $import->getPrivacy();
        if (is_int($privacy) || (is_string($privacy) && ctype_digit($privacy))) {
            $privacy = (int) $privacy;
            if (!array_key_exists($privacy, WorkspaceItemPrivacyInterface::KEYS)) {
                throw new InvalidImportEntryException(sprintf('Invalid privacy "%d"', $privacy));
            }
            $input->privacy = $privacy;
        } elseif (is_string($privacy)) {
            $input->privacyLabel = $privacy;
        }

        if (isset($entry['tags'])) {
            $input->tags = array_map(fn (string $tag): Tag => $this->resolveTag($workspaceId, $tag), $entry['tags']);
        }

        if (!empty($entry['attributes'])) {
            $input->attributes = [];
            foreach ($entry['attributes'] as $name => $value) {
                $attribute = new AttributeInput();
                $locale = null;
                if (false !== $pos = strrpos($name, ':')) {
                    $locale = substr($name, $pos + 1);
                    $name = substr($name, 0, $pos);
                }
                $attribute->name = $name;
                $attribute->locale = '' !== $locale ? $locale : null;
                $attribute->value = $value;
                $input->attributes[] = $attribute;
            }
        }

        if (isset($entry['sourceUrl'])) {
            $input->sourceFile = self::createSourceFile($entry['sourceUrl'], $entry['sourceName'] ?? null, $entry['sourceType'] ?? null);
        }

        $renditions = [];
        foreach (['preview' => 'previewUrl', 'thumbnail' => 'thumbnailUrl'] as $usedAs => $field) {
            if (isset($entry[$field])) {
                $definition = $this->renditionManager->getRenditionDefinitionsUsedAs($usedAs, $workspaceId)[0]
                    ?? throw new InvalidImportEntryException(sprintf('"%s": the workspace has no rendition definition used as %s', $field, $usedAs));
                $renditions[$definition->getId()] = $entry[$field];
            }
        }
        foreach ($entry['renditions'] ?? [] as $definitionRef => $url) {
            $renditions[$this->resolveRenditionDefinition($workspaceId, $definitionRef)->getId()] = $url;
        }
        if (!empty($renditions)) {
            $input->renditions = [];
            foreach ($renditions as $definitionId => $url) {
                $rendition = new AssetRenditionInput();
                $rendition->definitionId = $definitionId;
                $rendition->substituted = true;
                $rendition->sourceFile = self::createSourceFile($url);
                $input->renditions[] = $rendition;
            }
        }

        return $input;
    }

    private static function createSourceFile(string $url, ?string $originalName = null, ?string $type = null): FileSourceInput
    {
        $source = new FileSourceInput();
        $source->url = $url;
        $source->originalName = $originalName;
        $source->type = $type;
        $source->importFile = true;

        return $source;
    }

    private function resolveCollection(AssetImport $import, ?string $collectionRef, ?string $pathCollectionId): ?Collection
    {
        if (null !== $collectionRef) {
            $collectionId = basename($collectionRef);
            $collection = Uuid::isValid($collectionId) ? $this->em->find(Collection::class, $collectionId) : null;
            if (!$collection instanceof Collection
                || $collection->isDeleted()
                || $collection->getWorkspaceId() !== $import->getWorkspaceId()
            ) {
                throw new InvalidImportEntryException(sprintf('Collection "%s" not found in the workspace', $collectionRef));
            }

            return $collection;
        }

        if (null !== $pathCollectionId) {
            return $this->em->find(Collection::class, $pathCollectionId)
                ?? throw new InvalidImportEntryException('The collection of the "path" no longer exists');
        }

        return $import->getCollection();
    }

    private function resolveTag(string $workspaceId, string $tagRef): Tag
    {
        $tagId = basename($tagRef);
        $tag = Uuid::isValid($tagId)
            ? $this->em->getRepository(Tag::class)->findOneBy(['id' => $tagId, 'workspace' => $workspaceId])
            : null;

        $tag ??= $this->em->getRepository(Tag::class)->findOneBy(['name' => $tagRef, 'workspace' => $workspaceId]);
        if (!$tag instanceof Tag) {
            throw new InvalidImportEntryException(sprintf('Tag "%s" not found in the workspace', $tagRef));
        }

        return $tag;
    }

    /**
     * By key (e.g. "preview"), then by name (e.g. "Preview"), then by ID.
     */
    private function resolveRenditionDefinition(string $workspaceId, string $definitionRef): RenditionDefinition
    {
        $repository = $this->em->getRepository(RenditionDefinition::class);
        $definition = $repository->findOneBy(['workspace' => $workspaceId, 'key' => $definitionRef])
            ?? $repository->findOneBy(['workspace' => $workspaceId, 'name' => $definitionRef]);

        if (null === $definition && Uuid::isValid($definitionRef)) {
            $definition = $repository->findOneBy(['workspace' => $workspaceId, 'id' => $definitionRef]);
        }

        if (!$definition instanceof RenditionDefinition) {
            throw new InvalidImportEntryException(sprintf('Rendition definition "%s" not found in the workspace', $definitionRef));
        }

        return $definition;
    }
}
