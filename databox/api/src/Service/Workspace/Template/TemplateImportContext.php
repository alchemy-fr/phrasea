<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

/**
 * State shared by the sections during an import.
 *
 * Template items are identified by the id they had in the exported workspace.
 * Each imported entity is registered under that id so that later sections can resolve
 * their references, and the UUIDs embedded in free-form data (AQL conditions, integration
 * configs, …) can be translated to the ids of the imported entities.
 */
final class TemplateImportContext
{
    /**
     * @var array<string, array<string, object>>
     */
    private array $registry = [];

    /**
     * @var array<string, string> old UUID => new UUID
     */
    private array $idMap = [];

    /**
     * @var array<int, true> entities already matched by an item of the template
     */
    private array $claimed = [];

    public function __construct(
        public readonly Workspace $workspace,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function register(string $section, ?string $templateId, AbstractUuidEntity $entity): void
    {
        $this->claimed[spl_object_id($entity)] = true;

        if (null === $templateId || '' === $templateId) {
            return;
        }

        $this->registry[$section][$templateId] = $entity;
        if (Uuid::isValid($templateId) && $templateId !== $entity->getId()) {
            $this->idMap[$templateId] = $entity->getId();
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|null $class
     *
     * @return ($class is null ? object|null : T|null)
     */
    public function get(string $section, ?string $templateId, ?string $class = null): ?object
    {
        if (null === $templateId) {
            return null;
        }

        $entity = $this->registry[$section][$templateId] ?? null;
        if (null !== $class && !$entity instanceof $class) {
            return null;
        }

        return $entity;
    }

    /**
     * @return object[]
     */
    public function all(string $section): array
    {
        return array_values($this->registry[$section] ?? []);
    }

    /**
     * Id of the imported entity that replaces the one with the given template id.
     */
    public function getNewId(string $templateId): ?string
    {
        foreach ($this->registry as $entities) {
            if (isset($entities[$templateId])) {
                return $entities[$templateId]->getId();
            }
        }

        return null;
    }

    /**
     * Replaces the UUIDs of the exported entities by the ones of the imported entities.
     * UUIDs of entities that are not part of a template (e.g. collections) are left untouched.
     */
    public function remapIds(mixed $data): mixed
    {
        if ([] === $this->idMap) {
            return $data;
        }

        if (is_string($data)) {
            return strtr($data, $this->idMap);
        }
        if (is_array($data)) {
            return json_decode(strtr(json_encode($data, JSON_THROW_ON_ERROR), $this->idMap), true, 512, JSON_THROW_ON_ERROR);
        }

        return $data;
    }

    /**
     * Finds an existing entity matching the first criteria that gives a result, skipping
     * the entities already matched by another item of the template.
     * Null criteria (e.g. an optional key absent from the template) are ignored.
     *
     * @template T of object
     *
     * @param class-string<T>           $class
     * @param array<string, mixed>|null ...$criteriaList
     *
     * @return T|null
     */
    public function findExisting(string $class, ?array ...$criteriaList): ?object
    {
        foreach ($criteriaList as $criteria) {
            if (null === $criteria) {
                continue;
            }

            foreach ($this->em->getRepository($class)->findBy($criteria) as $entity) {
                if (!isset($this->claimed[spl_object_id($entity)])) {
                    return $entity;
                }
            }
        }

        return null;
    }
}
