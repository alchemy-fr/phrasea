<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Annotation\Groups;

final readonly class ResolveEntitiesOutput
{
    public const string GROUP_READ = 'resolve_entity_labels';

    public function __construct(
        #[Groups([self::GROUP_READ])]
        public array $entities,
    ) {
    }
}
