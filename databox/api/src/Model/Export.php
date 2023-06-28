<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Core\Annotation\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;

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
