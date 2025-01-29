<?php

declare(strict_types=1);

namespace App\Consumer\Handler\File;

use App\File\OrphanFileRemover;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class DeleteFilesIfOrphanHandler
{
    public function __construct(
        private OrphanFileRemover $orphanFileRemover,
    ) {
    }

    public function __invoke(DeleteFilesIfOrphan $message): void
    {
        foreach ($message->getIds() as $id) {
            $this->orphanFileRemover->removeIfOrphan($id);
        }
    }
}
