<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Attribute;
use App\Fixture\Faker\AssetAnnotationsFaker;
use Faker\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AssetAnnotationsValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public static function getAnnotatedClasses(): array
    {
        return [
            'attribute' => [Attribute::class, 'assetAnnotations'],
            'basket_asset' => [BasketAsset::class, 'assetAnnotations'],
            'attribute_input' => [AttributeInput::class, 'annotations'],
        ];
    }

    #[DataProvider('getAnnotatedClasses')]
    public function testFakerAnnotationsAreValid(string $class, string $property): void
    {
        $faker = new AssetAnnotationsFaker(Factory::create());

        foreach (['assetAnnotationsCue', 'assetAnnotationsCircle', 'assetAnnotationsTarget', 'assetAnnotationsRect', 'assetAnnotationsFace'] as $method) {
            $violations = $this->validator->validatePropertyValue($class, $property, $faker->$method());
            $this->assertCount(0, $violations, sprintf('%s: %s', $method, $violations));
        }
    }

    #[DataProvider('getAnnotatedClasses')]
    public function testListOfAnnotationsIsValid(string $class, string $property): void
    {
        $violations = $this->validator->validatePropertyValue($class, $property, [
            ['type' => 'cue', 't' => 1.5],
            ['type' => 'rect', 'x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.4],
            ['type' => 'face', 'x' => 0.5, 'y' => 0.1, 'w' => 0.2, 'h' => 0.3],
        ]);

        $this->assertCount(0, $violations, (string) $violations);
    }

    #[DataProvider('getAnnotatedClasses')]
    public function testNullIsValid(string $class, string $property): void
    {
        $this->assertCount(0, $this->validator->validatePropertyValue($class, $property, null));
    }

    #[DataProvider('getAnnotatedClasses')]
    public function testInvalidAnnotationIsRejected(string $class, string $property): void
    {
        $violations = $this->validator->validatePropertyValue($class, $property, [
            ['type' => 'cue', 't' => 1.5],
            ['type' => 'unknown'],
            ['t' => 2],
        ]);

        $this->assertCount(2, $violations, (string) $violations);
        $this->assertSame('[1][type]', $violations->get(0)->getPropertyPath());
        $this->assertSame('[2][type]', $violations->get(1)->getPropertyPath());
    }

    #[DataProvider('getAnnotatedClasses')]
    public function testSingleAnnotationObjectIsRejected(string $class, string $property): void
    {
        $violations = $this->validator->validatePropertyValue($class, $property, [
            'type' => 'cue',
            't' => 1.5,
        ]);

        $this->assertGreaterThan(0, $violations->count());
    }
}
