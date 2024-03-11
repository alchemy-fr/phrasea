<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Health;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

readonly class HealthChecker
{
    /**
     * @param HealthCheckerInterface[] $checkers
     */
    public function __construct(
        #[TaggedIterator(HealthCheckerInterface::TAG)]
        private iterable $checkers,
    )
    {
    }

    public function getChecks(): array
    {
        $checks = [];
        foreach ($this->checkers as $checker) {
            try {
                $ok = $checker->check();
                $check = [
                    'ok' => $ok,
                ];
            } catch (\Throwable $e) {
                $check = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }

            if (null !== $info = $checker->getAdditionalInfo()) {
                $check = array_merge($info, $check);
            }

            $checks[$checker->getName()] = $check;
        }

        return $checks;
    }
}
