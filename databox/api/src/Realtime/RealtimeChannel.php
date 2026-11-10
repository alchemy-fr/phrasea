<?php

declare(strict_types=1);

namespace App\Realtime;

/**
 * Names of the Pusher/Sockudo channels databox emits on.
 *
 * The Sockudo key is public: every channel is private, so that a subscription
 * goes through POST /pusher/auth (see EntityChannelAuthorizer).
 */
final class RealtimeChannel
{
    public const string WORKSPACE = 'private-workspace-';
    public const string ASSET = 'private-asset-';
    public const string FILE = 'private-file-';
    public const string BASKET = 'private-basket-';
    public const string EXPORT = 'private-export-';
    public const string WORKFLOW = 'private-workflow-';
    public const string THREAD = 'private-thread-';

    public static function workspace(string $workspaceId): string
    {
        return self::WORKSPACE.$workspaceId;
    }

    public static function asset(string $assetId): string
    {
        return self::ASSET.$assetId;
    }

    public static function file(string $fileId): string
    {
        return self::FILE.$fileId;
    }

    public static function basket(string $basketId): string
    {
        return self::BASKET.$basketId;
    }

    public static function export(string $exportId): string
    {
        return self::EXPORT.$exportId;
    }

    /**
     * The thread key (`<objectKey>:<objectId>`) becomes `<objectKey>.<objectId>` once the channel is normalized.
     */
    public static function thread(string $threadKey): string
    {
        return self::THREAD.$threadKey;
    }
}
