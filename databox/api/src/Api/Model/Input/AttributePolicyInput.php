<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Workspace;

class AttributePolicyInput
{
    /**
     * @var Workspace
     */
    public $workspace;

    /**
     * Target definition by name. Or use $definition.
     *
     * @var string|null
     */
    public $name;

    /**
     * @var bool
     */
    public $public;

    /**
     * @var bool
     */
    public $editable;

    /**
     * @var string|null
     */
    public $key;

    /**
     * @var array|null
     */
    public $labels;
}
