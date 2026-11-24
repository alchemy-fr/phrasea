<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Which batch action each quarantined asset is eligible to, so the client can
 * tell how many of the selection each action would process.
 */
final readonly class QuarantineBatchPrepareOutput
{
    public function __construct(
        /**
         * One entry per requested ID. `reasons` holds, for each action the
         * asset is not eligible to, why (`not_found`, `not_quarantined`,
         * `forbidden`, `no_duplicate`, `no_valid_target`). `duplicates` are
         * serialized like GET /assets/{id}/duplicates; a valid target is one
         * the incoming file can be added to as a new version.
         *
         * @var list<array{
         *     id: string,
         *     bypass: bool,
         *     trash: bool,
         *     delete: bool,
         *     mergeAsVersion: bool,
         *     mergeDiscard: bool,
         *     reasons: array<string, string>,
         *     duplicates: list<array{asset: \App\Entity\Core\Asset, analyzers: string[], validTarget: bool}>,
         * }>
         */
        #[Groups(['_'])]
        public array $items,
    ) {
    }
}
