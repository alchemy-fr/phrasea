<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\AttributeList\AttributeListItem;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class AttributeListItemInput
{
    public ?string $id = null;
    public ?string $definition = null;
    public ?string $key = null;
    public ?bool $displayEmpty = null;
    public ?string $format = null;

    #[Assert\NotNull]
    #[Assert\Choice(choices: AttributeListItem::TYPES)]
    public ?int $type = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        switch ($this->type) {
            case AttributeListItem::TYPE_ATTR_DEF:
                if (null === $this->definition) {
                    $context->buildViolation('The definition must be set.')
                        ->atPath('definition')
                        ->addViolation();
                }
                if (!empty($this->key)) {
                    $context->buildViolation('The key must not be set for definitions.')
                        ->atPath('key')
                        ->addViolation();
                }
                break;
            case AttributeListItem::TYPE_BUILT_IN:
                if (empty($this->key)) {
                    $context->buildViolation('The key must be set and not empty.')
                        ->atPath('key')
                        ->addViolation();
                }
                break;
            case AttributeListItem::TYPE_SPACER:
                if (!empty($this->key)) {
                    $context->buildViolation('The key must not be set for spacers.')
                        ->atPath('key')
                        ->addViolation();
                }
                break;
        }
    }
}
