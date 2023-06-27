<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

abstract class AbstractVoter extends Voter
{
    final public const CREATE = 'CREATE';
    final public const LIST = 'LIST';
    final public const READ = 'READ';
    final public const EDIT = 'EDIT';
    final public const DELETE = 'DELETE';
    final public const EDIT_PERMISSIONS = 'EDIT_PERMISSIONS';

    protected EntityManagerInterface $em;
    protected Security $security;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }
}
