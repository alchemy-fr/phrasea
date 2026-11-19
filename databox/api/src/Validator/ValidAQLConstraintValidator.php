<?php

declare(strict_types=1);

namespace App\Validator;

use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\AttributeSearch;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidAQLConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AttributeSearch $attributeSearch,
    ) {
    }

    /**
     * @param string|null        $value
     * @param ValidAQLConstraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (empty($value)) {
            return;
        }

        try {
            $this->attributeSearch->buildConditionQuery(
                $this->attributeSearch->buildAllAttributeDefinitionsGroups(),
                (string) $value,
                // Validates attribute filter rules, applied as trusted conditions
                [AQLToESQuery::OPT_TRUSTED_CONDITIONS => true]
            );
        } catch (\Throwable $e) {
            $this->context
                ->buildViolation(sprintf(
                    'Invalid AQL condition: %s',
                    $e->getMessage()
                ))
                ->addViolation();
        }
    }
}
