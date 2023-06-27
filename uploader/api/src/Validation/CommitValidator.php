<?php

declare(strict_types=1);

namespace App\Validation;

use App\Entity\Commit;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CommitValidator
{
    public function __construct(private readonly ?int $maxCommitSize, private readonly ?int $maxFileCount)
    {
    }

    public function validate(Commit $commit): void
    {
        if (null !== $this->maxFileCount && count($commit->getFiles()) > $this->maxFileCount) {
            throw new BadRequestHttpException(sprintf('Number of files exceeded (%d > %d)', count($commit->getFiles()), $this->maxFileCount));
        }

        if (null !== $this->maxCommitSize && $commit->getTotalSize() > $this->maxCommitSize) {
            throw new BadRequestHttpException(sprintf('Max commit size exceeded (%d > %d)', $commit->getTotalSize(), $this->maxCommitSize));
        }
    }
}
