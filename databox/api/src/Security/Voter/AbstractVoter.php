<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Contracts\Service\Attribute\Required;

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

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }
}
