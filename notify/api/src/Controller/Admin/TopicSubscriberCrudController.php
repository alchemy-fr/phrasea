<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use App\Entity\TopicSubscriber;
use App\Topic\TopicManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TopicSubscriberCrudController extends AbstractAdminCrudController
{
    private TopicManager $topicManager;

    public function __construct(TopicManager $topicManager)
    {
        $this->topicManager = $topicManager;
    }

    public static function getEntityFqcn(): string
    {
        return TopicSubscriber::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('TopicSubscriber')
            ->setEntityLabelInPlural('TopicSubscriber')
            ->setSearchFields(['id', 'topic']);
    }

    public function configureFields(string $pageName): iterable
    {
        $topic = TextField::new('topic');
        $contact = AssociationField::new('contact');
        $id = IdField::new();
        $createdAt = DateTimeField::new('createdAt');
        $contactEmail = TextareaField::new('contact.email');
        $contactPhone = TextareaField::new('contact.phone');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $topic, $contactEmail, $contactPhone, $createdAt];
        }
        elseif (Crud::PAGE_DETAIL === $pageName) {
            return [$id, $topic, $createdAt, $contact];
        }
        elseif (Crud::PAGE_NEW === $pageName) {
            return [$topic, $contact];
        }
        elseif (Crud::PAGE_EDIT === $pageName) {
            return [$topic, $contact];
        }

        return [];
    }

    /**
     * @param TopicSubscriber $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->topicManager->addSubscriber($entityInstance->getContact(), $entityInstance->getTopic());
    }
}
