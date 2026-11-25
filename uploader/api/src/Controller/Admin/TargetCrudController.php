<?php

namespace App\Controller\Admin;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\CodeField;
use Alchemy\AdminBundle\Field\GroupChoiceField;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AdminBundle\Field\JsonField;
use App\Consumer\Handler\AssetConsumerNotifyHandler;
use App\Entity\Target;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;

class TargetCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Target::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Target')
            ->setEntityLabelInPlural('Targets')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['id', 'slug', 'name', 'description', 'targetUrl', 'defaultDestination', 'authorizationScheme', 'allowedGroups']);
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return self::setAuthorizationKeyOnSubmit(parent::createNewFormBuilder($entityDto, $formOptions, $context));
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return self::setAuthorizationKeyOnSubmit(parent::createEditFormBuilder($entityDto, $formOptions, $context));
    }

    /**
     * The authorization key field is never filled with the saved key: an empty one keeps it. Set before the validation
     * of the target.
     */
    private static function setAuthorizationKeyOnSubmit(FormBuilderInterface $builder): FormBuilderInterface
    {
        return $builder->addEventListener(FormEvents::POST_SUBMIT, static function (PostSubmitEvent $event): void {
            $form = $event->getForm();
            $target = $form->getData();
            $key = $form->has('authorizationKey') ? $form->get('authorizationKey')->getData() : null;
            if ($target instanceof Target && is_string($key) && '' !== $key) {
                $target->setAuthorizationKey($key);
            }
        }, 1);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(TextFilter::new('slug'))
            ->add(BooleanFilter::new('enabled'))
            ->add(BooleanFilter::new('hidden'))
            ->add(NullFilter::new('targetUrl', 'Mode')->setChoiceLabels('Pull', 'Push'))
            ->add(DateTimeFilter::new('createdAt'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new()
            ->hideOnForm();
        yield TextField::new('slug');
        yield TextField::new('name');
        yield TextareaField::new('description')
            ->hideOnIndex();
        yield CodeField::new('pullModeUrl', 'Pull mode URL')
            ->onlyOnDetail();
        yield TextField::new('targetUrl')
            ->setHelp('Leave empty for pull mode. i.e: "https://phraseanet.phrasea.local/api/v1/upload/enqueue/" for Phraseanet. https only: the authorization key is sent to it (http is accepted for a host of the stack, without dot).');
        yield TextField::new('authorizationScheme')
            ->setHelp('Use "OAuth" for Phraseanet')
            ->setFormTypeOptions(['attr' => ['placeholder' => 'Defaults to "'.AssetConsumerNotifyHandler::DEFAULT_AUTHORIZATION_SCHEME.'"']])
            ->onlyOnForms();
        yield TextField::new('authorizationKey')
            ->setFormType(PasswordType::class)
            ->setFormTypeOptions(['mapped' => false, 'required' => false, 'always_empty' => true, 'attr' => ['autocomplete' => 'new-password']])
            ->setHelp('Never shown. Leave empty to keep the current key; it is removed when the target URL moves to another host (scheme, host or port) without a new key.')
            ->onlyOnForms();
        yield TextField::new('defaultDestination')
            ->setHelp('i.e: "42" (for Phraseanet collection), "cdc3679f-3f37-4260-8de7-b649ecc8c1cc" (for Databox collection)')
            ->hideOnIndex();
        yield GroupChoiceField::new('allowedGroups')
            ->hideOnIndex();
        yield BooleanField::new('enabled');
        yield BooleanField::new('hidden')
            ->setHelp('Hide this target from the list of available targets');
        yield BooleanField::new('hasForm', 'Form')
            ->renderAsSwitch(false)
            ->onlyOnIndex();
        yield BooleanField::new('hasData', 'Data')
            ->renderAsSwitch(false)
            ->onlyOnIndex();
        yield JsonField::new('form')
            ->setHelp('The upload form, designed with the form builder of the Uploader client. "null" for no form.')
            ->hideOnIndex();
        yield JsonField::new('data')
            ->setHelp('Merged into the form data of every upload, over the values entered by the user. "null" for no data.')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt')
            ->hideOnForm();
    }
}
