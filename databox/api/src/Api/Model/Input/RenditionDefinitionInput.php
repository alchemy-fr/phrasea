<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Security\Voter\RenditionDefinitionVoter;
use Symfony\Component\Serializer\Attribute\Groups;

class RenditionDefinitionInput
{
    private const string GRANT_ADMIN_PROP = 'object ? is_granted(\''.RenditionDefinitionVoter::READ_ADMIN.'\', object) : true';

    /**
     * @var Workspace|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $workspace;

    /**
     * @var RenditionDefinition|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $parent;

    /**
     * @var RenditionPolicy|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $policy;

    /**
     * @var string
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $name;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $download;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $substitutable;

    /**
     * Whether the asset attribute values are embedded into the rendition file on export.
     *
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $writeMetadata;

    /**
     * Map of metadata tag group id (e.g. "IPTC:CopyrightNotice") to hardcoded value,
     * written into the exported rendition file.
     *
     * @var array<string, string>|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $metadata;

    /**
     * @var int
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    #[ApiProperty(security: self::GRANT_ADMIN_PROP)]
    public $buildMode;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    #[ApiProperty(security: self::GRANT_ADMIN_PROP)]
    public $useAsMain;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    #[ApiProperty(security: self::GRANT_ADMIN_PROP)]
    public $useAsPreview;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    #[ApiProperty(security: self::GRANT_ADMIN_PROP)]
    public $useAsThumbnail;

    /**
     * @var bool
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    #[ApiProperty(security: self::GRANT_ADMIN_PROP)]
    public $useAsAnimatedThumbnail;

    /**
     * @var string|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    #[ApiProperty(security: self::GRANT_ADMIN_PROP)]
    public $definition = '';

    /**
     * @var int|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    #[ApiProperty(security: self::GRANT_ADMIN_PROP)]
    public $priority;

    /**
     * @var string|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $key;

    /**
     * @var array|null
     */
    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public $labels;

    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public ?array $translations = null;

    #[Groups([RenditionDefinition::GROUP_WRITE])]
    public ?int $target = null;
}
