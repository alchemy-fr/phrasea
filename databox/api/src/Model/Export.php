<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Controller\Core\ExportAction;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'export',
    operations: [
        new Get(),
        new Post(
            uriTemplate: '/export',
            controller: ExportAction::class,
            read: true,
            validate: false
        ),
    ],
    normalizationContext: [
        'groups' => ['export:output'],
    ],
    denormalizationContext: [
        'groups' => ['export:input'],
    ],
)]
class Export
{
    #[ApiProperty(identifier: true)]
    public string $id = 'export';

    /**
     * @var string[]
     */
    #[Groups('export:input')]
    public $assets;

    /**
     * @var string[]
     */
    #[Groups('export:input')]
    public $renditions;

    #[Groups('export:output')]
    public ?string $downloadUrl = null;
}
