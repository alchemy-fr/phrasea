<?php

declare(strict_types=1);

namespace App\Service\Share;

use App\Entity\Core\AssetAttachment;
use App\Entity\Core\AssetRendition;

/**
 * An attachment a share link delivers, through a rendition of the attached asset.
 */
final readonly class SharedAttachment
{
    public function __construct(
        public AssetAttachment $attachment,
        public AssetRendition $rendition,
    ) {
    }
}
