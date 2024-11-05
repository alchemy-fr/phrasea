<?php

namespace Alchemy\ConfiguratorBundle\Controller;

use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\SuperAdminVoter;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("'.SuperAdminVoter::ROLE.'") or is_granted("'.JwtUser::ROLE_TECH.'")'))]
class ConfiguratorEntryCrudController extends AbstractCrudController
{
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public static function getEntityFqcn(): string
    {
        return ConfiguratorEntry::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name');
        yield TextareaField::new('value')
            ->hideOnIndex()
            ->setFormTypeOption('attr', [
                'rows' => 20,
                'style' => 'font-family: monospace;',
            ])
        ;
        yield DateTimeField::new('createdAt')
        ->hideOnForm();
        yield DateTimeField::new('updatedAt')
        ->hideOnForm();
    }
}
