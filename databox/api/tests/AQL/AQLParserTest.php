<?php

namespace App\Tests\AQL;

use App\Elasticsearch\AQL\AQLParser;
use PHPUnit\Framework\TestCase;

class AQLParserTest extends TestCase
{
    public function testParse(): void
    {
        $parser = new AQLParser(true);
        $parser->parse('foo = bar');
    }
}
