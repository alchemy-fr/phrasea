<?php

declare(strict_types=1);

namespace App\Security;

use App\Elasticsearch\AQL\UnsupportedEvaluationException;
use App\Elasticsearch\BuiltInAttribute\PositionBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\ScoreBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\SimilarBuiltInAttribute;

/**
 * The built-in fields that an attribute filter rule cannot use: the score and the position depend on
 * the search request, not on the asset, and @similar reads an asset while its query is built, which
 * evaluates the rules again, endlessly.
 */
final class AttributeFilterRuleFields
{
    /**
     * @param array $ast the parsed condition (AQLParser)
     *
     * @throws UnsupportedEvaluationException
     */
    public static function assertSupported(array $ast): void
    {
        if (null !== $field = self::findUnsupportedField($ast)) {
            throw new UnsupportedEvaluationException(sprintf('Field "%s" cannot be used in an attribute filter rule', $field));
        }
    }

    private static function findUnsupportedField(array $node): ?string
    {
        if (isset($node['field']) && is_string($node['field'])
            && in_array(strtolower($node['field']), [
                SimilarBuiltInAttribute::getKey(),
                ScoreBuiltInAttribute::getKey(),
                PositionBuiltInAttribute::getKey(),
            ], true)) {
            return $node['field'];
        }

        foreach ($node as $child) {
            if (is_array($child) && null !== $field = self::findUnsupportedField($child)) {
                return $field;
            }
        }

        return null;
    }
}
