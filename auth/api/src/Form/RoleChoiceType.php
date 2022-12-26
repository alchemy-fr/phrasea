<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RoleChoiceType extends AbstractType
{
    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = [
            'Admin' => 'ROLE_ADMIN',
            'Super Admin' => 'ROLE_SUPER_ADMIN',
            'Users/Groups management' => 'ROLE_ADMIN_USERS',
            'Developer / Ops' => 'ROLE_TECH',
        ];

        $resolver->setDefaults([
            'multiple' => true,
            'expanded' => true,
            'choices' => array_filter($choices, function (string $role) {
                return $this->authorizationChecker->isGranted($role)
                    || $this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN');
            }),
        ]);
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}
