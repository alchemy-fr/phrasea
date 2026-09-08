<?php

declare(strict_types=1);

namespace App\Service\Metadata;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\Value\Binary;
use Psr\Log\LoggerInterface;

final readonly class MetadataNormalizer
{
    public function __construct(
        private MetadataManipulator $metadataManipulator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * normalize metadata from metadataManipulator bundle (for File.metadata).
     *
     * The map is grouped by tag namespace: [ '<group>' => [ '<name>' => [values...] ] ].
     */
    public function normalize(MetadataBag $bag): array
    {
        $a = [];

        /** @var Metadata $meta */
        foreach ($bag as $meta) {
            $vMeta = $meta->getValue();

            // skip "declared-binary" and "binary-not-declared-binary" data
            if ($vMeta instanceof Binary) {
                continue;
            }
            try {
                if (!json_encode($vMeta->asString(), JSON_THROW_ON_ERROR)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            [$group, $name] = explode(':', $meta->getTagGroup()->getId(), 2);
            $a[$group][$name] = $vMeta->asArray();
        }

        return $a;
    }

    public function denormalize(array $data): MetadataBag
    {
        $bag = new MetadataBag();

        foreach ($data as $group => $tags) {
            if ('System' === $group || !is_array($tags)) {
                continue;
            }

            foreach ($tags as $name => $values) {
                if (!is_array($values)) {
                    continue;
                }

                $tagGroupId = $group.':'.$name;

                try {
                    $meta = $this->metadataManipulator->createMetadata($tagGroupId);
                } catch (\Throwable $e) {
                    // an unknown tag must not sink the whole bag
                    $this->logger->warning('Skipping unknown metadata tag', [
                        'exception' => $e,
                        'tag' => $tagGroupId,
                    ]);

                    continue;
                }

                if (!$meta->getTagGroup()->isWritable()) {
                    continue;
                }

                if ($meta->getTagGroup()->isMulti()) {
                    $meta->setValue($values);
                } else {
                    $meta->setValue(reset($values));
                }

                $bag->add($meta);
            }
        }

        return $bag;
    }
}
