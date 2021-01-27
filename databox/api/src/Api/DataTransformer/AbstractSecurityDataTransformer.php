<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use Symfony\Component\Security\Core\Security;

abstract class AbstractSecurityDataTransformer implements DataTransformerInterface
{
    private Security $security;

    protected function isGranted(string $attribute, object $object): bool
    {
        return $this->security->isGranted($attribute, $object);
    }

    /**
     * @required
     */
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }
}
