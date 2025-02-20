<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Entity\Template\WorkspaceTemplate;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class WorkspaceTemplateCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return WorkspaceTemplate::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name');
        yield JsonField::new('data')
            ->hideOnIndex();
    }
}
