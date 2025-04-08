<?php

namespace App\Elasticsearch\AQL\Function;

final readonly class DateAddFunction extends AbstractDateFunction
{
    public function resolve(array $arguments): mixed
    {
        return $this->normalizeDate($arguments[0])->add(new \DateInterval($arguments[1]));
    }

    public static function getName(): string
    {
        return 'date_add';
    }

    public static function getArguments(): array
    {
        return [
            new Argument('date', TypeEnum::DATE, 'The date to which the interval will be added.'),
            new Argument('interval', TypeEnum::STRING, 'The interval to add.'),
        ];
    }

    public function getScript(array $arguments): string
    {
        throw new \InvalidArgumentException(sprintf('Script generation is not supported for %s yet', self::getName()));
    }
}
