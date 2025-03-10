<?php

namespace App\Elasticsearch\AQL;

use hafriedlander\Peg\Compiler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class AQLParser
{
    public function __construct(
        #[Autowire(param: 'app.debug')]
        private bool $debug = false
    )
    {
    }

    public function parse(string $condition): void
    {
        $this->compile();

        $result = (new AQLGrammar($condition))->match_expression();

        dump($result);
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
