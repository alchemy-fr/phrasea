<?php

namespace Alchemy\AdminBundle\Field;

use Alchemy\AdminBundle\Utils\UserChoiceLoader;
use Alchemy\AdminBundle\Utils\UserDisplayNameResolver;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A user ID shown as the user name. The index and detail pages only resolve the users they
 * display, in a single call, and show the ID itself for a user that cannot be resolved; the
 * forms offer the choice among every user.
 */
final class UserChoiceField implements ResetInterface
{
    /**
     * @var array<string, true>
     */
    private array $pendingIds = [];

    /**
     * @var array<string, string>
     */
    private array $names = [];

    public function __construct(
        private readonly UserChoiceLoader $userChoiceLoader,
        private readonly UserDisplayNameResolver $userDisplayNameResolver,
        private readonly AdminContextProviderInterface $adminContextProvider,
    ) {
    }

    public function create(string $propertyName, ?string $label = null): ChoiceField
    {
        $field = ChoiceField::new($propertyName, $label);

        $page = $this->adminContextProvider->getContext()?->getCrud()?->getCurrentPage();
        if (in_array($page, [Crud::PAGE_INDEX, Crud::PAGE_DETAIL], true)) {
            return $field->setTranslatableChoices(fn (?object $entity, FieldDto $field): array => $this->getDisplayChoices($field->getValue()));
        }

        return $field->setChoices(fn (?object $entity, FieldDto $field): array => $this->getFormChoices($field->getValue()));
    }

    /**
     * @return array<string, UserLabel>
     */
    private function getDisplayChoices(mixed $userId): array
    {
        if (null === $userId || '' === $userId) {
            return [];
        }

        $userId = (string) $userId;
        if (!isset($this->names[$userId])) {
            $this->pendingIds[$userId] = true;
        }

        return [$userId => new UserLabel($userId, $this->getName(...))];
    }

    private function getName(string $userId): string
    {
        if ([] !== $this->pendingIds) {
            $ids = array_map(strval(...), array_keys($this->pendingIds));
            $this->pendingIds = [];
            $names = $this->userDisplayNameResolver->resolve($ids)['names'];
            foreach ($ids as $id) {
                $this->names[$id] = $names[$id] ?? $id;
            }
        }

        return $this->names[$userId] ?? $userId;
    }

    /**
     * @return array<string, string>
     */
    private function getFormChoices(mixed $userId): array
    {
        $users = $this->userChoiceLoader->getUsers() ?? [];
        if (null !== $userId && '' !== $userId && !isset($users[(string) $userId])) {
            $users[(string) $userId] = (string) $userId;
        }

        $choices = [];
        foreach ($users as $id => $name) {
            $choices[$name] = (string) $id;
        }

        return $choices;
    }

    public function reset(): void
    {
        $this->pendingIds = [];
        $this->names = [];
    }
}
