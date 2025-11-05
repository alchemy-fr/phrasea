<?php

namespace Alchemy\ConfiguratorBundle\Controller;

use Alchemy\AdminBundle\Controller\AbstractAdminCrudController;
use Alchemy\AdminBundle\Field\IdField;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Voter\SuperAdminVoter;
use Alchemy\ConfiguratorBundle\Entity\ConfiguratorEntry;
use Alchemy\ConfiguratorBundle\Field\FileField;
use Alchemy\ConfiguratorBundle\Form\Type\ConfigurationKeyType;
use Alchemy\ConfiguratorBundle\Message\DeployConfig;
use Alchemy\ConfiguratorBundle\Service\ConfigurationReference;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("'.SuperAdminVoter::ROLE.'") or is_granted("'.JwtUser::ROLE_TECH.'")'))]
class ConfiguratorEntryCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly ConfigurationReference $configurationReference,
    ) {
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $allowedKeys = array_keys($this->configurationReference->getAllSchemaProperties());
        dump($allowedKeys);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, static function (Action $action) use ($allowedKeys) {
                $action->displayIf(static function (ConfiguratorEntry $entity) use ($allowedKeys) {
                    return in_array($entity->getName(), $allowedKeys, true);
                });

                return $action;
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, static function (Action $action) use ($allowedKeys) {
                $action->displayIf(static function (ConfiguratorEntry $entity) use ($allowedKeys) {
                    return in_array($entity->getName(), $allowedKeys, true);
                });

                return $action;
            })
            ->add(Crud::PAGE_INDEX, Action::new('push', 'Push Configuration', 'fa fa-upload')
            ->createAsGlobalAction()
            ->linkToCrudAction('configuratorPush')
            );
    }

    public function configuratorPush(AdminContext $context): Response
    {
        $this->bus->dispatch(new DeployConfig());
        $this->addFlash('success', 'Configuration Push scheduled');

        return $this->returnToReferer($context);
    }

    public static function getEntityFqcn(): string
    {
        return ConfiguratorEntry::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new();
        yield TextField::new('name')
            ->hideWhenUpdating()
            ->setFormType(ConfigurationKeyType::class)
        ;
        yield FileField::new('file')
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('mapped', false)
            ->onlyOnForms();
        yield TextField::new('value')
            ->hideOnIndex()
            ->setFormType(TextareaType::class)
            ->addCssClass('field-textarea')
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
