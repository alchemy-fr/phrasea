<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Filter\UserChoiceFilter;
use Alchemy\AdminBundle\Utils\UserChoiceLoader;
use Alchemy\AuthBundle\Repository\UserRepositoryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UserChoiceFilterTest extends TestCase
{
    private function createFilterFactory(UserRepositoryInterface $repository, ?Request $request = null): UserChoiceFilter
    {
        $requestStack = new RequestStack();
        if (null !== $request) {
            $requestStack->push($request);
        }

        return new UserChoiceFilter(new UserChoiceLoader($repository, new ArrayAdapter(), new NullLogger()), $requestStack);
    }

    public function testOffersEveryUser(): void
    {
        $users = UserChoiceLoaderTest::createUsers(UserChoiceLoader::PAGE_SIZE + 10);
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('getUsers')
            ->willReturnCallback(fn (array $options): array => array_slice($users, $options['query']['first'], $options['query']['max']));

        $dto = $this->createFilterFactory($repository)->createFilter('ownerId', 'Owner')->getAsDto();

        $this->assertSame(ChoiceFilter::class, $dto->getFqcn());
        $choices = $dto->getFormTypeOption('value_type_options.choices');
        $this->assertCount(UserChoiceLoader::PAGE_SIZE + 10, $choices);
        $this->assertSame('u'.(UserChoiceLoader::PAGE_SIZE + 9), $choices['user'.(UserChoiceLoader::PAGE_SIZE + 9)]);
        $this->assertSame('true', $dto->getFormTypeOption('value_type_options.attr')['data-ea-autocomplete-allow-item-create']);
    }

    public function testKeepsARequestedIdMissingFromTheList(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('getUsers')->willReturn([['id' => 'u1', 'username' => 'alice']]);
        $request = new Request(['filters' => [
            'subscriber:userId' => ['comparison' => '=', 'value' => 'deleted-user'],
        ]]);

        $dto = $this->createFilterFactory($repository, $request)->createFilter('subscriber.userId')->getAsDto();

        $this->assertSame([
            'alice' => 'u1',
            'deleted-user' => 'deleted-user',
        ], $dto->getFormTypeOption('value_type_options.choices'));
    }

    public function testFallsBackToAnIdFilterWhenTheUsersCannotBeListed(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('getUsers')->willThrowException(new \RuntimeException('Keycloak is down'));

        $dto = $this->createFilterFactory($repository)->createFilter('ownerId', 'Owner')->getAsDto();

        $this->assertSame(TextFilter::class, $dto->getFqcn());
        $this->assertSame('ownerId', $dto->getProperty());
        $this->assertSame('choice', $dto->getFormTypeOption('comparison_type_options.type'));
    }
}
