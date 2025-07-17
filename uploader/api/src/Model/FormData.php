<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\ValidateFormAction;
use App\Entity\Target;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/form/validate',
            controller: ValidateFormAction::class,
            description: 'Retrieve form schema'
        ),
    ]
)]
final class FormData
{
    #[Assert\NotNull]
    private ?array $data = null;

    #[Assert\NotNull]
    private ?Target $target = null;

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getTarget(): Target
    {
        return $this->target;
    }

    public function setTarget(Target $target): void
    {
        $this->target = $target;
    }
}
