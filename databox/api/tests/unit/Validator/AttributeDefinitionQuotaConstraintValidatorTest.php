<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Workspace;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Validator\AttributeDefinitionQuotaConstraint;
use App\Validator\AttributeDefinitionQuotaConstraintValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AttributeDefinitionQuotaConstraintValidator>
 */
class AttributeDefinitionQuotaConstraintValidatorTest extends ConstraintValidatorTestCase
{
    private const int LIMIT = 3;

    private int $count = 0;
    private bool $managed = false;

    protected function createValidator(): AttributeDefinitionQuotaConstraintValidator
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('contains')->willReturnCallback(fn (): bool => $this->managed);

        $repository = $this->createStub(AttributeDefinitionRepository::class);
        $repository->method('countWorkspaceDefinitions')->willReturnCallback(fn (): int => $this->count);

        return new AttributeDefinitionQuotaConstraintValidator($em, $repository, self::LIMIT);
    }

    private static function createDefinition(): AttributeDefinition
    {
        $definition = new AttributeDefinition();
        $definition->setWorkspace(new Workspace());

        return $definition;
    }

    public function testANewDefinitionIsAcceptedBelowTheLimit(): void
    {
        $this->count = self::LIMIT - 1;

        $this->validator->validate(self::createDefinition(), new AttributeDefinitionQuotaConstraint());

        $this->assertNoViolation();
    }

    public function testANewDefinitionIsRefusedAtTheLimit(): void
    {
        $this->count = self::LIMIT;
        $constraint = new AttributeDefinitionQuotaConstraint();

        $this->validator->validate(self::createDefinition(), $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ limit }}', (string) self::LIMIT)
            ->assertRaised();
    }

    public function testAnExistingDefinitionStaysEditableBeyondTheLimit(): void
    {
        $this->count = self::LIMIT + 10;
        $this->managed = true;

        $this->validator->validate(self::createDefinition(), new AttributeDefinitionQuotaConstraint());

        $this->assertNoViolation();
    }
}
