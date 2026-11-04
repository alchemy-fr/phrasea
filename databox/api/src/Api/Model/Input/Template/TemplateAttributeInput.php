<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Template;

use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Entity\Template\AssetDataTemplate;

class TemplateAttributeInput extends AbstractBaseAttributeInput
{
    /**
     * @var AssetDataTemplate
     */
    public $template;
}
