<?php

namespace App\Elasticsearch\AQL\Function;

final readonly class DateSubFunction extends AbstractDateFunction
{
    public function resolve(array $arguments): mixed
    {
        return $this->normalizeDate($arguments[0])->sub(new \DateInterval($arguments[1]));
    }

    public static function getName(): string
    {
        return 'date_sub';
    }

    public static function getArguments(): array
    {
        return [
            new Argument('date', TypeEnum::DATE, 'The date to which the interval will be subtracted.'),
            new Argument('interval', TypeEnum::STRING, 'The interval to sub.'),
        ];
    }

    public function getScript(array $arguments): string
    {
        throw new \InvalidArgumentException(sprintf('Script generation is not supported for %s yet', self::getName()));
    }
}
