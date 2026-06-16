<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\PrivacyAttributeType;
use App\Entity\Core\WorkspaceItemPrivacyInterface;

class PrivacyAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new PrivacyAttributeType();
    }

    public function getValidationCases(): array
    {
        return [
            ['',  ['Invalid privacy value']],
            [WorkspaceItemPrivacyInterface::SECRET, null],
            [WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE, null],
            [WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE, null],
            [WorkspaceItemPrivacyInterface::PRIVATE, null],
            [WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS, null],
            [WorkspaceItemPrivacyInterface::PUBLIC, null],
            [WorkspaceItemPrivacyInterface::PUBLIC + 42, ['Invalid privacy value']],
            ['foo', ['Invalid privacy value']],
            [false, ['Invalid privacy value']],
            [true, ['Invalid privacy value']],
        ];
    }

    public function getNormalizationCases(): array
    {
        return [
            ...parent::getNormalizationCases(),
            '0_string' => ['0', 0],
        ];
    }
}
