<?php

declare(strict_types=1);

namespace App\Mcp\Tool;

use Alchemy\McpBundle\Api\ApiClient;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * The discussion of an asset is a thread of messages, keyed by the asset ("asset:<ID>") and created by its
 * first message. Whoever can read the asset reads and posts in its discussion.
 */
final readonly class DiscussionTools
{
    public function __construct(
        private ApiClient $client,
    ) {
    }

    /**
     * @return array{items: list<array>, totalItems: int|null, page: int, hasNextPage: bool}
     */
    #[McpTool(
        name: 'list_thread_messages',
        description: 'List the messages of a discussion thread, the oldest first, with their author and attachments. The thread of an asset is "thread.id" of get_asset, null while nobody has posted in it.',
        annotations: new ToolAnnotations(readOnlyHint: true),
    )]
    public function listMessages(
        #[Schema(description: 'Thread ID (UUID)')]
        string $threadId,
        #[Schema(minimum: 1)]
        int $page = 1,
    ): array {
        return $this->client->getCollection('/threads/'.rawurlencode($threadId).'/messages', ['page' => $page]);
    }

    #[McpTool(
        name: 'post_message',
        description: 'Post a message in the discussion of an asset, as the current user: the participants of the discussion, the mentioned users and the followers of the asset and of its collections are notified. Give the threadKey of the asset ("threadKey" of get_asset, e.g. "asset:<asset ID>"), which also starts the discussion, or the ID of an existing thread. Returns the message with its thread.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false),
    )]
    public function post(
        #[Schema(description: 'Text of the message; a user is mentioned (and notified) with "@[<name>](<user ID>)"')]
        string $content,
        #[Schema(description: 'Key of the discussion: "threadKey" of get_asset')]
        ?string $threadKey = null,
        #[Schema(description: 'ID of an existing thread, instead of threadKey')]
        ?string $threadId = null,
    ): array {
        return $this->client->post('/messages', ApiClient::withoutNulls([
            'content' => $content,
            'threadKey' => $threadKey,
            'threadId' => $threadId,
        ]));
    }
}
