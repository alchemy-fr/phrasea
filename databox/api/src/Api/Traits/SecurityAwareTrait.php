<?php

declare(strict_types=1);

namespace App\Api\Traits;

use App\Security\Voter\ChuckNorrisVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;

trait SecurityAwareTrait
{
    protected Security $security;

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    protected function isChuckNorris(): bool
    {
        return $this->security->isGranted(ChuckNorrisVoter::ROLE);
    }
}
