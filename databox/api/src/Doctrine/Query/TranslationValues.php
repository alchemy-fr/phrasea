<?php

declare(strict_types=1);

namespace App\Doctrine\Query;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * TRANSLATION_VALUES(translationsColumn, 'field').
 *
 * Returns every translation of `field` stored in a TranslationsTrait JSON column
 * (`{"field": {"fr": "…", "de": "…"}}`), decoded, or NULL when there is none. Only the values are
 * returned, never the locale keys. They are joined by U+001F, a character nobody types, so that a
 * LIKE pattern never spans two values.
 *
 * The JSON is stored with escaped unicode (`é`), so a LIKE on the raw column text would not
 * match accented values: the values are decoded by the JSON functions of the platform.
 */
final class TranslationValues extends FunctionNode
{
    private Node|string $column;
    private string $field;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->column = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $parser->match(TokenType::T_STRING);
        $field = (string) $parser->getLexer()->token?->value;
        if (1 !== preg_match('#^[a-z_]+$#', $field)) {
            throw QueryException::semanticalError(sprintf('TRANSLATION_VALUES(): invalid translation field "%s"', $field));
        }
        $this->field = $field;
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $column = $sqlWalker->walkStringPrimary($this->column);
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            // An emptied translation is stored as `[]`, on which json_each_text() fails
            $fieldValue = sprintf("%s -> '%s'", $column, $this->field);

            return sprintf(
                "(SELECT string_agg(tv_.value, chr(31)) FROM json_each_text(CASE WHEN json_typeof(%1\$s) = 'object' THEN %1\$s END) tv_)",
                $fieldValue,
            );
        }

        if ($platform instanceof SQLitePlatform) {
            return sprintf(
                "(SELECT group_concat(value, char(31)) FROM json_each(%s, '$.%s'))",
                $column,
                $this->field,
            );
        }

        throw new \LogicException(sprintf('TRANSLATION_VALUES() is not supported on %s', $platform::class));
    }
}
