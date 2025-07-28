<?php

namespace App\Api\DTO\Input;

use App\Entity\Target;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class DownloadUrlInput
{
    #[Assert\NotNull]
    public ?string $url = null;

    public ?string $targetSlug = null;

    public ?Target $target = null;

    public ?array $data = null;
    public ?array $formData = null;
    public ?string $schemaId = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null === $this->target && empty($this->targetSlug)) {
            $context->buildViolation('The target slug cannot be empty.')
                ->atPath('targetSlug')
                ->addViolation();
        }
    }
}
