<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Integration\WorkspaceIntegration;

final readonly class WorkspaceDefaults
{
    public function __construct(
        public RenditionPolicy $renditionPolicy,
        /**
         * @var array<string, RenditionDefinition> indexed by rendition key
         */
        public array $renditionDefinitions,
        public AttributePolicy $attributePolicy,
        public AttributeDefinition $nameAttributeDefinition,
        /**
         * @var array<string, WorkspaceIntegration> indexed by integration key
         */
        public array $integrations,
    ) {
    }
}
