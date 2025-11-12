<?php

namespace Alchemy\ConfiguratorBundle\Dumper;

use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntryRepository;

final readonly class JsonDumper
{
    public function __construct(
        private ConfiguratorEntryRepository $repository,
    ) {
    }

    public function dump(): string
    {
        $entries = $this->repository->findAll();

        $data = [];
        foreach ($entries as $entry) {
            $key = $entry->getName();

            $parts = explode('.', $key);
            $ref = &$data;
            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (!isset($ref[$part])) {
                    $ref[$part] = [];
                }

                if (!is_array($ref[$part])) {
                    throw new \RuntimeException("Cannot set value for key '{$key}': part '{$part}' is already set as a value.");
                }

                $ref = &$ref[$part];
            }
            $key = array_shift($parts);

            $ref[$key] = $entry->getValue();
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
