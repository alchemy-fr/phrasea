<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Consumer\Handler\Search\ESPopulateHandler;
use App\Entity\Admin\PopulatePass;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class PopulatePassCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return PopulatePass::class;
    }

    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator, private readonly EventProducer $eventProducer)
    {
    }

    public function configureActions(Actions $actions): Actions
    {
        $globalAddPopulateAction = Action::new('AddPopulate')
            ->linkToCrudAction('addPopulate')
            ->createAsGlobalAction();

        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, $globalAddPopulateAction)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('PopulatePass')
            ->setEntityLabelInPlural('PopulatePass')
            ->setSearchFields(['id', 'documentCount', 'progress', 'indexName', 'mapping', 'error']);
    }

    public function configureFields(string $pageName): iterable
    {
        $endedAt = DateTimeField::new('endedAt');
        $documentCount = IntegerField::new('documentCount');
        $progress = IntegerField::new('progress');
        $indexName = TextField::new('indexName');
        $error = TextField::new('error');
        $createdAt = DateTimeField::new('createdAt');
        $id = IdField::new();
        $mapping = JsonField::new('mapping');
        $progressString = TextareaField::new('progressString');
        $timeTakenUnit = TextareaField::new('timeTakenUnit');
        $successful = BooleanField::new('successful')->renderAsSwitch(false);

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $indexName, $progressString, $timeTakenUnit, $endedAt, $successful, $error, $createdAt];
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $endedAt, $documentCount, $progress, $indexName, $mapping, $error, $createdAt];
        } elseif (Crud::PAGE_NEW === $pageName) {
            return [$endedAt, $documentCount, $progress, $indexName, $error, $createdAt];
        } elseif (Crud::PAGE_EDIT === $pageName) {
            return [$endedAt, $documentCount, $progress, $indexName, $error, $createdAt];
        }

        return [];
    }

    public function addPopulate(): Response
    {
        $this->eventProducer->publish(ESPopulateHandler::createEvent());

        $this->addFlash('info', 'Populate command was triggered');

        $url = $this->adminUrlGenerator
            ->setController(PopulatePassCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }
}
