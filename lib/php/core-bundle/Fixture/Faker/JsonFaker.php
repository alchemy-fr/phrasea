<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

class JsonFaker extends AbstractCachedFaker
{
    public function largeFileMetadata(): string
    {
        $stream = $this->download('json', 'json', 'https://microsoftedge.github.io/Demos/json-dummy-data/1MB.json');

        return stream_get_contents($stream);
    }

    public function largeJSONFileMetadata(): array
    {
        return json_decode($this->largeFileMetadata(), true, 512, JSON_THROW_ON_ERROR);
    }
}
