<?php

declare(strict_types=1);

namespace App\Model;

/**
 * The values are stored in audit_log.action: never change nor reuse one.
 */
enum AuditLogActionEnum: int
{
    case AssetMoved = 1;
    case AssetAddedToCollection = 2;
    case AssetRemovedFromCollection = 3;
    case AssetSubstituted = 4;
    case AssetAttributeChanged = 5;
    case ObjectCreated = 6;
    case ObjectUpdated = 7;
    case ObjectDeleted = 8;
    case ObjectTrashed = 9;
    case ObjectRestored = 10;
    case CollectionMoved = 11;
    case AssetQuarantineBypassed = 12;
    case BasketArchived = 13;
    case BasketUnarchived = 14;
    case BasketAssetsAdded = 15;
    case BasketAssetsRemoved = 16;
    case ProfileUpdated = 17;
    case RenditionDeleted = 18;
    case AssetCopied = 19;
    case PermissionGranted = 20;
    case PermissionChanged = 21;
    case PermissionRevoked = 22;
    case AssetDownloaded = 23;
    case WorkflowTriggered = 24;
    case WorkflowJobRerun = 25;
    case WorkflowCancelled = 26;
    case AutomationTriggered = 27;
    case WorkspaceTermsSigned = 28;

    /**
     * The stable identifier of the action exposed by the API (e.g. "asset_moved").
     */
    public function getKey(): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $this->name));
    }

    public static function tryFromKey(string $key): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getKey() === $key) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public static function getKeys(): array
    {
        return array_map(static fn (self $case): string => $case->getKey(), self::cases());
    }

    /**
     * @return array<string, self> label => case
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[ucfirst(str_replace('_', ' ', $case->getKey()))] = $case;
        }

        return $choices;
    }
}
