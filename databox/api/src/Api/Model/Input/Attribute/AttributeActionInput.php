<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Attribute;

use App\Api\Model\Input\InputLimits;
use App\Attribute\BatchAttributeManager;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AttributeActionInput extends AbstractExtendedAttributeInput
{
    /**
     * Maximum length of the pattern of a regex "replace".
     */
    public const int REGEX_MAX_LENGTH = 1000;

    /**
     * Options documented for PostgreSQL regexp_replace().
     */
    public const string REGEX_FLAGS_PATTERN = '/^[bcegimnpqstwx]{0,10}$/';

    /**
     * Attribute ID.
     */
    public ?string $id = null;

    /**
     * Attribute IDs.
     */
    #[Assert\Count(max: InputLimits::MAX_IDS)]
    public ?array $ids = null;

    /**
     * Asset IDs.
     *
     * @var string[]
     */
    #[Assert\Count(max: InputLimits::MAX_IDS)]
    public ?array $assets = null;

    /**
     * Available actions:
     *  - "set"
     *  - "add"
     *  - "delete"
     *  - "replace".
     *
     * Default is "set"
     */
    public ?string $action = 'set';

    public ?bool $regex = null;

    /**
     * Regex flags (e.g. 'g').
     */
    public ?string $flags = null;

    public ?string $replaceWith = null;

    #[Assert\Callback]
    public function validateRegexReplace(ExecutionContextInterface $context): void
    {
        if (BatchAttributeManager::ACTION_REPLACE !== $this->action || true !== $this->regex) {
            return;
        }

        if (!is_string($this->value)) {
            $context->buildViolation('The regex pattern should be a string.')
                ->atPath('value')
                ->addViolation();
        } elseif (mb_strlen($this->value) > self::REGEX_MAX_LENGTH) {
            $context->buildViolation('The regex pattern is too long ({{ limit }} characters or less).')
                ->setParameter('{{ limit }}', (string) self::REGEX_MAX_LENGTH)
                ->atPath('value')
                ->addViolation();
        }

        if (null !== $this->flags && !preg_match(self::REGEX_FLAGS_PATTERN, $this->flags)) {
            $context->buildViolation('Invalid regex flags.')
                ->atPath('flags')
                ->addViolation();
        }
    }
}
