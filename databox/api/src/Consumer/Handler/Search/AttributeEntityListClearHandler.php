<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Service\Asset\Attribute\AttributeEntity\EntityListClearer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttributeEntityListClearHandler
{
    public function __construct(
        private EntityListClearer $clearer,
    ) {
    }

    public function __invoke(AttributeEntityListClear $message): void
    {
        $this->clearer->clear($message->getListId(), $message->getWorkspaceId());
    }
}
