<?php

namespace App\Controller\Admin;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Entity\Core\MetadataTag;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\JsonResponse;

class MetadataTagController extends AbstractCrudController
{
    private array $knowTags;

    public function __construct(MetadataManipulator $metadataManipulator)
    {
        $this->knowTags = array_keys($metadataManipulator->getKnownTagGroups());
    }

    public static function getEntityFqcn(): string
    {
        return MetadataTag::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('MetadataTag')
            ->setEntityLabelInPlural('MetadataTags')
            ->setSearchFields(['id', 'label', 'className'])
            ->setPaginatorPageSize(100);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('id');
        yield TextField::new('label');
        yield TextField::new('className');
    }

    public function autocomplete(AdminContext $context): JsonResponse
    {
        // /!\ The highlight / option-selection upon AssociationField/autocomplete seems done in js, using the passed query.
        // For multiple word search (AND), this works ONLY if delimiter is SPACE.
        // searching "dic da" must find "DICOM:ContentDate"
        $q = array_map(
            function($e) { return '.*' . preg_quote($e) . '.*'; },
            explode(' ', $context->getSearch()->getQuery())     // /!\ can't use ":"
        );
        $q = join('', $q);

        $a = array_values(preg_filter(
            "/^" . $q . "/i",
            "$0",
            $this->knowTags
        ));

        // todo: quick&dirty build of response. We should use paginator etc.

        $a = array_map(
            function($e) { return [EA::ENTITY_ID => $e, 'entityAsString' => $e]; },
            $a
        );

        return new JsonResponse(['results' => $a]);
    }
}
