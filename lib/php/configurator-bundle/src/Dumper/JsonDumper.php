<?php

namespace Alchemy\ConfiguratorBundle\Dumper;

use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntryRepository;

final readonly class JsonDumper
{
    public function __construct(
        private ConfiguratorEntryRepository $repository,
    )
    {
    }

    public function dump(): string
    {
        $entries = $this->repository->findAll();

        $data = [];
        foreach ($entries as $entry) {
            $data[$entry->getName()] = $entry->getValue();
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
