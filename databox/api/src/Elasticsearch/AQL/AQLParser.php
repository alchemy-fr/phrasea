<?php

namespace App\Elasticsearch\AQL;

use hafriedlander\Peg\Compiler;

readonly class AQLParser
{
    public function __construct(
        private bool $debug = false,
    ) {
    }

    public function parse(string $condition): ?array
    {
        $this->compile();

        $result = (new AQLGrammar(trim($condition)))->match_main();
        if (false === $result) {
            return null;
        }

        return $result;
    }

    private function compile(): void
    {
        $compiledFile = __DIR__.'/AQLGrammar.php';
        if ($this->debug || !file_exists($compiledFile)) {
            file_put_contents($compiledFile, Compiler::compile(sprintf('<?php

namespace App\Elasticsearch\AQL;

use hafriedlander\Peg\Parser;

class AQLGrammar extends Parser\Basic
{
/*!* AQLGrammar
%s
*/
}', file_get_contents(__DIR__.'/AQLGrammar.peg'))));
        }

        require_once $compiledFile;
    }
}
