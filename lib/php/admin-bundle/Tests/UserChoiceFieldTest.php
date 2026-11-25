<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Field\UserChoiceField;
use Alchemy\AdminBundle\Field\UserLabel;
use Alchemy\AdminBundle\Utils\UserChoiceLoader;
use Alchemy\AdminBundle\Utils\UserDisplayNameResolver;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserChoiceFieldTest extends TestCase
{
    private function createFieldFactory(UserRepositoryInterface $repository, string $page): UserChoiceField
    {
        $crud = new CrudDto();
        $crud->setPageName($page);
        $context = $this->createStub(AdminContextInterface::class);
        $context->method('getCrud')->willReturn($crud);
        $contextProvider = $this->createStub(AdminContextProviderInterface::class);
        $contextProvider->method('getContext')->willReturn($context);

        return new UserChoiceField(
            new UserChoiceLoader($repository, new ArrayAdapter(), new NullLogger()),
            new UserDisplayNameResolver($repository, new NullLogger()),
            $contextProvider,
        );
    }

    private function getChoices(ChoiceField $field, mixed $value): array
    {
        $fieldDto = new FieldDto();
        $fieldDto->setValue($value);

        return $field->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES)(null, $fieldDto);
    }

    private function render(UserLabel $label): string
    {
        return $label->trans($this->createStub(TranslatorInterface::class));
    }

    public function testResolvesTheUsersOfThePageInASingleCall(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->never())->method('getUsers');
        $repository->expects($this->once())
            ->method('getUsersByIds')
            ->with(['u1', 'unknown', 'u2'])
            ->willReturn([
                'u1' => ['id' => 'u1', 'username' => 'jdoe', 'firstName' => 'John', 'lastName' => 'Doe'],
                'u2' => ['id' => 'u2', 'username' => '<b>bob</b>'],
            ]);

        $field = $this->createFieldFactory($repository, Crud::PAGE_INDEX)->create('ownerId', 'Owner');

        $labels = [];
        foreach (['u1', 'unknown', 'u1', 'u2'] as $userId) {
            $choices = $this->getChoices($field, $userId);
            $this->assertSame([$userId], array_keys($choices));
            $labels[] = $choices[$userId];
        }
        $this->assertSame([], $this->getChoices($field, null));

        $this->assertSame([
            'jdoe (John Doe)',
            'unknown',
            'jdoe (John Doe)',
            '&lt;b&gt;bob&lt;/b&gt;',
        ], array_map($this->render(...), $labels));
    }

    public function testShowsTheIdWhenTheUsersCannotBeResolved(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getUsersByIds')
            ->willThrowException(new \RuntimeException('Keycloak is down'));

        $field = $this->createFieldFactory($repository, Crud::PAGE_DETAIL)->create('ownerId');

        $this->assertSame('u1', $this->render($this->getChoices($field, 'u1')['u1']));
    }

    public function testFormOffersEveryUserAndKeepsTheCurrentOne(): void
    {
        $users = UserChoiceLoaderTest::createUsers(UserChoiceLoader::PAGE_SIZE + 10);
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects($this->never())->method('getUsersByIds');
        $repository->method('getUsers')
            ->willReturnCallback(fn (array $options): array => array_slice($users, $options['query']['first'], $options['query']['max']));

        $field = $this->createFieldFactory($repository, Crud::PAGE_EDIT)->create('ownerId');

        $choices = $this->getChoices($field, 'deleted-user');
        $this->assertCount(UserChoiceLoader::PAGE_SIZE + 11, $choices);
        $this->assertSame('u0', $choices['user0']);
        $this->assertSame('deleted-user', $choices['deleted-user']);
    }

    public function testFormKeepsTheCurrentUserWhenTheUsersCannotBeListed(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('getUsers')->willThrowException(new \RuntimeException('Keycloak is down'));

        $field = $this->createFieldFactory($repository, Crud::PAGE_EDIT)->create('ownerId');

        $this->assertSame(['u1' => 'u1'], $this->getChoices($field, 'u1'));
    }
}
