<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Api\Processor\ExportProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'export',
    operations: [
        new Get(),
        new Post(
            uriTemplate: '/export',
            processor: ExportProcessor::class,
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
