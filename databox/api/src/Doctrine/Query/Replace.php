<?php

declare(strict_types=1);

namespace App\Doctrine\Query;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * REPLACE(string, from, to).
 */
final class Replace extends FunctionNode
{
    private Node|string $string;
    private Node|string $from;
    private Node|string $to;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->string = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->from = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->to = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'REPLACE('.$sqlWalker->walkStringPrimary($this->string).', '.$sqlWalker->walkStringPrimary($this->from).', '.$sqlWalker->walkStringPrimary($this->to).')';
    }
}
