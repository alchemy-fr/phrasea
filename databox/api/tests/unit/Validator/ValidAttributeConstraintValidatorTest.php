<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\BooleanAttributeType;
use App\Attribute\Type\DateTimeAttributeType;
use App\Attribute\Type\EntityAttributeType;
use App\Attribute\Type\GeoPointAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\PrivacyAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\Workspace;
use App\Http\LocaleContext;
use App\Repository\Core\AttributeEntityRepository;
use App\Validator\ValidAttributeConstraint;
use App\Validator\ValidAttributeConstraintValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ValidAttributeConstraintValidator>
 */
class ValidAttributeConstraintValidatorTest extends ConstraintValidatorTestCase
{
    private const string ENTITY_ID = '17e9152c-fa5f-4f53-83ac-62674f6f6f8d';

    protected function createValidator(): ValidAttributeConstraintValidator
    {
        $entity = new AttributeEntity();
        $entity->setWorkspace(new Workspace());
        $entity->setValue('Entity');

        $repository = $this->createStub(AttributeEntityRepository::class);
        $repository->method('find')->willReturnCallback(
            static fn (string $id): ?AttributeEntity => self::ENTITY_ID === $id ? $entity : null
        );

        $entityType = new EntityAttributeType($repository);
        $entityType->setLocaleContext(new LocaleContext(new RequestStack()));

        return new ValidAttributeConstraintValidator(new AttributeTypeRegistry([
            BooleanAttributeType::NAME => new BooleanAttributeType(),
            DateTimeAttributeType::NAME => new DateTimeAttributeType(),
            EntityAttributeType::NAME => $entityType,
            GeoPointAttributeType::NAME => new GeoPointAttributeType(),
            NumberAttributeType::NAME => new NumberAttributeType(),
            PrivacyAttributeType::NAME => new PrivacyAttributeType(),
            TextAttributeType::NAME => new TextAttributeType(),
        ]));
    }

    #[DataProvider('getValidValues')]
    public function testValidValue(string $type, string $value): void
    {
        $this->validator->validate($this->createAttribute($type, $value), new ValidAttributeConstraint());

        $this->assertNoViolation();
    }

    public static function getValidValues(): array
    {
        return [
            'entity' => [EntityAttributeType::NAME, self::ENTITY_ID],
            'boolean' => [BooleanAttributeType::NAME, '1'],
            'date_time' => [DateTimeAttributeType::NAME, '2024-01-02T03:04:05+00:00'],
            'geo_point' => [GeoPointAttributeType::NAME, '48.8566,2.3522'],
            'number' => [NumberAttributeType::NAME, '42'],
            'privacy' => [PrivacyAttributeType::NAME, '2'],
            'text' => [TextAttributeType::NAME, 'foo'],
        ];
    }

    #[DataProvider('getInvalidValues')]
    public function testInvalidValue(string $type, string $value, string $message): void
    {
        $this->validator->validate($this->createAttribute($type, $value), new ValidAttributeConstraint());

        $this->buildViolation($message)
            ->atPath('property.path.value')
            ->setInvalidValue($value)
            ->assertRaised();
    }

    public static function getInvalidValues(): array
    {
        return [
            'entity' => [EntityAttributeType::NAME, 'not-a-uuid', 'Invalid entity ID'],
            'number' => [NumberAttributeType::NAME, 'abc', 'Invalid number'],
            'privacy' => [PrivacyAttributeType::NAME, '42', 'Invalid privacy value'],
        ];
    }

    public function testInvalidValueIsAcceptedWhenAllowed(): void
    {
        $attribute = $this->createAttribute(EntityAttributeType::NAME, 'not-a-uuid');
        $attribute->getDefinition()->setAllowInvalid(true);

        $this->validator->validate($attribute, new ValidAttributeConstraint());

        $this->assertNoViolation();
    }

    private function createAttribute(string $type, string $value): Attribute
    {
        $definition = new AttributeDefinition();
        $definition->setType($type);

        $attribute = new Attribute();
        $attribute->setDefinition($definition);
        $attribute->setValue($value);

        return $attribute;
    }
}
