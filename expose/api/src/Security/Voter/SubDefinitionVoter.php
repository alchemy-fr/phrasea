<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\SubDefinition;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SubDefinitionVoter extends Voter
{
    use JwtVoterTrait;

    final public const string READ = 'READ';
    final public const string EDIT = 'EDIT';
    final public const string DELETE = 'DELETE';
    final public const string CREATE = 'CREATE';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, SubDefinition::class, true);
    }

    protected function supports($attribute, $subject): bool
    {
        return $subject instanceof SubDefinition;
    }

    /**
     * @param SubDefinition $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::READ => $this->security->isGranted(AssetVoter::READ, $subject->getAsset()),
            self::CREATE, self::DELETE, self::EDIT => $this->security->isGranted(AssetVoter::EDIT, $subject->getAsset()),
            default => false,
        };
    }
}
