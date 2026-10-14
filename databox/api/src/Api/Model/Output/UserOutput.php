<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Attribute\Groups;

final class UserOutput
{
    #[Groups(['_'])]
    public ?string $id = null;

    #[Groups(['_'])]
    public ?string $username = null;

    #[Groups(['_'])]
    public ?string $firstName = null;

    #[Groups(['_'])]
    public ?string $lastName = null;

    /**
     * "First Last" when Keycloak knows the names, the username otherwise.
     */
    #[Groups(['_'])]
    public ?string $displayName = null;

    #[Groups(['_'])]
    public ?bool $removed = false;
}
