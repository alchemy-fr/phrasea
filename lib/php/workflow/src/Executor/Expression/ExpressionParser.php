<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Executor\Expression;

use Alchemy\Workflow\State\JobState;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ExpressionParser extends ExpressionLanguage
{
    public function __construct(CacheItemPoolInterface $cache = null, array $providers = [])
    {
        parent::__construct($cache, $providers);
    }

    public function evaluateStepWith(string $expression, JobState $jobState): mixed
    {
        return $this->evaluate($expression, [
            'steps' =>
        ]);
    }
}
