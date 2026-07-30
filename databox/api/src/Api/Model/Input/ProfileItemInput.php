<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Profile\ProfileItem;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ProfileItemInput
{
    public ?string $id = null;
    public ?string $definition = null;
    public ?string $key = null;
    public ?bool $displayEmpty = null;
    public ?string $format = null;

    /**
     * Grid placement: ['region' => ..., 'anchor' => ..., 'order' => ...].
     *
     * @var array{region: string, anchor: string, order?: int}|null
     */
    public ?array $placement = null;
    public ?string $variant = null;
    public ?bool $showLabel = null;
    public ?bool $showIcon = null;
    // Boolean type: render a true/false icon instead of text.
    public ?bool $booleanIcon = null;
    // AttributeEntity type: 'full' | 'emoji' | 'color'.
    public ?string $entityDisplay = null;

    #[Assert\NotNull]
    #[Assert\Choice(choices: ProfileItem::SECTIONS)]
    public ?int $section = null;

    #[Assert\NotNull]
    #[Assert\Choice(choices: ProfileItem::TYPES)]
    public ?int $type = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        switch ($this->type) {
            case ProfileItem::TYPE_ATTR_DEF:
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
            case ProfileItem::TYPE_BUILT_IN:
                if (empty($this->key)) {
                    $context->buildViolation('The key must be set and not empty.')
                        ->atPath('key')
                        ->addViolation();
                }
                break;
            case ProfileItem::TYPE_SPACER:
                if (!empty($this->key)) {
                    $context->buildViolation('The key must not be set for spacers.')
                        ->atPath('key')
                        ->addViolation();
                }
                break;
        }

        if (ProfileItem::SECTION_GRID === $this->section) {
            if (null === $this->placement) {
                $context->buildViolation('A placement is required for grid items.')
                    ->atPath('placement')
                    ->addViolation();

                return;
            }

            $region = $this->placement['region'] ?? null;
            $anchor = $this->placement['anchor'] ?? null;
            if (!in_array($region, ProfileItem::REGIONS, true)) {
                $context->buildViolation(sprintf('Invalid grid region "%s".', (string) $region))
                    ->atPath('placement')
                    ->addViolation();
            } elseif (!in_array($anchor, ProfileItem::ANCHORS[$region], true)) {
                $context->buildViolation(sprintf('Invalid anchor "%s" for region "%s".', (string) $anchor, $region))
                    ->atPath('placement')
                    ->addViolation();
            }
        }
    }
}
