<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Template;

use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Entity\Template\AssetDataTemplate;
use Symfony\Component\Validator\Constraints as Assert;

class TemplateAttributeInput extends AbstractBaseAttributeInput
{
    /**
     * @var AssetDataTemplate
     */
    #[Assert\NotNull]
    public $template;
}
