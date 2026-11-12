<?php

declare(strict_types=1);

namespace App\Api\DTO\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class TargetDataInput
{
    public const string GROUP = 'target:data:w';

    /**
     * The values merged into the form data of every commit, null (or an empty object) to remove them.
     */
    #[Groups([self::GROUP])]
    public ?array $data = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->data && [] !== $this->data && array_is_list($this->data)) {
            $context->buildViolation('Expected an object')
                ->atPath('data')
                ->addViolation();
        }
    }
}
