<?php

declare(strict_types=1);

namespace App\Doctrine\Query;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * PostgreSQL REGEXP_REPLACE(string, pattern, replacement [, flags]).
 */
final class RegexpReplace extends FunctionNode
{
    private Node|string $string;
    private Node|string $pattern;
    private Node|string $replacement;
    private Node|string|null $flags = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->string = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->pattern = $parser->StringExpression();
        $parser->match(TokenType::T_COMMA);
        $this->replacement = $parser->StringExpression();
        if ($parser->getLexer()->isNextToken(TokenType::T_COMMA)) {
            $parser->match(TokenType::T_COMMA);
            $this->flags = $parser->StringExpression();
        }
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $arguments = [$this->string, $this->pattern, $this->replacement];
        if (null !== $this->flags) {
            $arguments[] = $this->flags;
        }

        return 'REGEXP_REPLACE('.implode(', ', array_map(static fn (Node|string $argument): string => $sqlWalker->walkStringPrimary($argument), $arguments)).')';
    }
}
