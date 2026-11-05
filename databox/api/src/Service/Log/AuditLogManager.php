<?php

declare(strict_types=1);

namespace App\Service\Log;

use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\TrackBundle\AlchemyTrackBundle;
use App\Audit\AuditContext;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Entity\Log\AuditLog;
use App\Model\AuditActorTypeEnum;
use App\Model\AuditLogActionEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Proxy;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Writes the audit log.
 *
 * Outside of a Doctrine onFlush listener ($inOnFlush = false), the entry is only persisted:
 * the caller flushes it, after its own changes (or after a DQL/SQL write, which the
 * UnitOfWork does not see).
 */
final class AuditLogManager
{
    /**
     * Turned off by the cascades (deletion of a workspace, a collection...) that log a single entry on their root.
     */
    public static bool $enabled = true;

    /**
     * @var array<string, int>|null the auditable objects deleted while muted, by object type key
     */
    private static ?array $mutedDeletions = null;

    private const int LABEL_MAX_LENGTH = 255;

    public function __construct(
        #[Autowire(service: AlchemyTrackBundle::OBJECT_MAPPING_SERVICE_ID)]
        private readonly ObjectMapping $objectMapping,
        private readonly EntityManagerInterface $em,
        private readonly AuditContext $auditContext,
    ) {
    }

    public function log(
        AuditLogActionEnum $action,
        ?object $object,
        array $data = [],
        bool $inOnFlush = false,
        ?string $workspaceId = null,
    ): ?AuditLog {
        if (null === $object) {
            return $this->logRaw($action, null, null, null, $workspaceId, $data, $inOnFlush);
        }

        return $this->logRaw(
            $action,
            $object::class,
            method_exists($object, 'getId') ? (string) $object->getId() : null,
            $this->resolveLabel($object),
            $workspaceId ?? $this->resolveWorkspaceId($object),
            $data,
            $inOnFlush,
        );
    }

    /**
     * For an object already deleted or not loaded.
     *
     * @param class-string|null $objectClass
     */
    public function logRaw(
        AuditLogActionEnum $action,
        ?string $objectClass,
        ?string $objectId,
        ?string $objectLabel,
        ?string $workspaceId,
        array $data = [],
        bool $inOnFlush = false,
    ): ?AuditLog {
        if (!self::$enabled) {
            return null;
        }

        $context = $this->auditContext->resolve();

        $log = new AuditLog();
        $log->setAction($action);
        $log->setObjectType(null !== $objectClass ? $this->getObjectType($objectClass) : null);
        $log->setObjectId($objectId);
        $log->setObjectLabel($objectLabel);
        $log->setWorkspaceId($workspaceId);
        $log->setData(AuditValueNormalizer::normalize($data));

        $log->setActorType(AuditActorTypeEnum::from($context['actorType']));
        $log->setUserId($context['userId']);
        $log->setUserName($context['userName']);
        $log->setImpersonatorId($context['impersonatorId']);
        $log->setClientId($context['clientId']);
        $log->setIp($context['ip']);
        $log->setCorrelationId($context['correlationId']);
        $log->setMeta(array_filter([
            'uri' => $context['uri'],
            '_route' => $context['route'],
            'source' => $context['source'],
            'command' => $context['command'],
        ], static fn (mixed $v): bool => null !== $v));

        if ($inOnFlush) {
            $uow = $this->em->getUnitOfWork();
            $uow->persist($log);
            $uow->computeChangeSet($this->em->getClassMetadata(AuditLog::class), $log);
        } else {
            $this->em->persist($log);
        }

        return $log;
    }

    /**
     * Runs a cascade without logging each object it changes.
     *
     * @return array<string, int> the number of auditable objects deleted meanwhile, by object type key
     */
    public function mute(callable $fn): array
    {
        $wasEnabled = self::$enabled;
        $previousCounts = self::$mutedDeletions;
        self::$enabled = false;
        self::$mutedDeletions = [];

        try {
            $fn();

            $counts = self::$mutedDeletions;
            ksort($counts);

            return $counts;
        } finally {
            self::$enabled = $wasEnabled;
            // A nested cascade counts in the enclosing one
            self::$mutedDeletions = null !== $previousCounts
                ? self::mergeCounts($previousCounts, self::$mutedDeletions ?? [])
                : null;
        }
    }

    /**
     * Called by the listener for each auditable object deleted while the log is muted.
     */
    public function countMutedDeletion(object $object): void
    {
        if (null === self::$mutedDeletions) {
            return;
        }

        $key = self::getObjectTypeKey($object::class);
        self::$mutedDeletions[$key] = (self::$mutedDeletions[$key] ?? 0) + 1;
    }

    private static function mergeCounts(array $a, array $b): array
    {
        foreach ($b as $key => $count) {
            $a[$key] = ($a[$key] ?? 0) + $count;
        }

        return $a;
    }

    public function isObjectAudited(object|string $object): bool
    {
        return $this->objectMapping->isObjectMapped($object);
    }

    /**
     * @param class-string $objectClass
     */
    public function getObjectType(string $objectClass): ?int
    {
        if (!$this->objectMapping->isObjectMapped($objectClass)) {
            return null;
        }

        return (int) $this->objectMapping->getObjectKey($objectClass);
    }

    /**
     * The key of an object type exposed by the API: the short class name in snake case (e.g. "saved_search").
     */
    public static function getObjectTypeKey(string $objectClass): string
    {
        if (false !== $pos = strrpos($objectClass, '\\'.Proxy::MARKER.'\\')) {
            $objectClass = substr($objectClass, $pos + Proxy::MARKER_LENGTH + 2);
        }
        $shortName = substr($objectClass, (int) strrpos($objectClass, '\\') + (str_contains($objectClass, '\\') ? 1 : 0));

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
    }

    /**
     * @return array<string, int> object type key => stored object type
     */
    public function getObjectTypeKeys(): array
    {
        $keys = [];
        foreach ($this->objectMapping->getObjectTypes() as $type) {
            $keys[self::getObjectTypeKey($this->objectMapping->getClassName($type))] = (int) $type;
        }

        return $keys;
    }

    public function getObjectTypeKeyOf(?int $objectType): ?string
    {
        if (null === $objectType || !in_array($objectType, $this->objectMapping->getObjectTypes(), true)) {
            return null;
        }

        return self::getObjectTypeKey($this->objectMapping->getClassName($objectType));
    }

    public function resolveWorkspaceId(object $object): ?string
    {
        if ($object instanceof Workspace) {
            return $object->getId();
        }

        if (method_exists($object, 'getWorkspace')) {
            $workspace = $object->getWorkspace();

            return $workspace instanceof Workspace ? $workspace->getId() : null;
        }

        return null;
    }

    /**
     * The value of the "use as name" attribute of highest priority, among the attributes already
     * loaded: the AssetNameResolver would fill the attribute caches in the middle of a flush,
     * before the new attributes are written.
     */
    private static function resolveAssetTitle(Asset $asset, bool $loadAttributes): ?string
    {
        $attributes = $asset->getAttributes();
        if ($attributes instanceof PersistentCollection && !$attributes->isInitialized() && !$loadAttributes) {
            return null;
        }

        $title = null;
        $priority = null;
        foreach ($attributes as $attribute) {
            $namePriority = $attribute->getDefinition()?->getNamePriority();
            if (null !== $namePriority && (null === $priority || $namePriority > $priority) && is_string($attribute->getValue())) {
                $title = $attribute->getValue();
                $priority = $namePriority;
            }
        }

        return $title;
    }

    /**
     * @param bool $loadAttributes whether the attributes of an asset may be loaded to find its title (never during a flush)
     */
    public function resolveLabel(object $object, bool $loadAttributes = false): ?string
    {
        $label = null;
        if ($object instanceof Asset) {
            $label = $object->name ?? self::resolveAssetTitle($object, $loadAttributes) ?? $object->getSource()?->getOriginalName();
        } elseif (method_exists($object, 'getTitle')) {
            $label = $object->getTitle();
        } elseif (method_exists($object, 'getName')) {
            $label = $object->getName();
        } elseif ($object instanceof \Stringable) {
            $label = (string) $object;
            if (method_exists($object, 'getId') && $label === (string) $object->getId()) {
                $label = null;
            }
        }

        if (!is_string($label) || '' === $label) {
            return null;
        }

        return mb_substr($label, 0, self::LABEL_MAX_LENGTH);
    }
}
