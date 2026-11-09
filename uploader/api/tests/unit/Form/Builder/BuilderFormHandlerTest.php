<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\Builder;

use App\Form\Builder\BuilderFormHandler;
use App\Form\Builder\FormDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class BuilderFormHandlerTest extends TestCase
{
    private function definition(): FormDefinition
    {
        return FormDefinition::fromArray([
            'version' => 1,
            'fields' => [
                ['id' => '1', 'source' => 'custom', 'key' => 'title', 'type' => 'text', 'required' => true, 'constraints' => ['minLength' => 2]],
                ['id' => '2', 'source' => 'custom', 'key' => 'count', 'type' => 'number', 'constraints' => ['min' => 1, 'max' => 3]],
                ['id' => '3', 'source' => 'custom', 'key' => 'agreed', 'type' => 'boolean', 'required' => true],
                ['id' => '4', 'source' => 'custom', 'key' => 'dest', 'type' => 'choice', 'multiple' => true, 'choices' => [['value' => 'a'], ['value' => 'b']]],
                ['id' => '5', 'source' => 'custom', 'key' => 'date', 'type' => 'date'],
                ['id' => '6', 'source' => 'custom', 'key' => 'at', 'type' => 'date_time'],
                ['id' => '7', 'source' => 'custom', 'key' => 'color', 'type' => 'color'],
                ['id' => '8', 'source' => 'custom', 'key' => 'origin', 'type' => 'text', 'hidden' => true, 'defaultValue' => 'web'],
                ['id' => '9', 'source' => 'layout', 'kind' => 'section'],
            ],
        ]);
    }

    private function handler(): BuilderFormHandler
    {
        return new BuilderFormHandler(Validation::createValidator());
    }

    public function testPrepare(): void
    {
        $this->assertSame([
            'title' => 'Foo',
            'count' => 2,
            'agreed' => true,
            'dest' => ['a'],
            'origin' => 'web',
        ], $this->handler()->prepare($this->definition(), [
            'title' => ' Foo ',
            'count' => '2',
            'agreed' => 'true',
            'dest' => 'a',
            'date' => '',
            'color' => null,
            'origin' => 'forged',
            'unknown' => 'x',
        ]));
    }

    public function testValidate(): void
    {
        $handler = $this->handler();

        $this->assertSame([], $handler->validate($this->definition(), [
            'title' => 'Foo',
            'count' => 3,
            'agreed' => true,
            'dest' => ['a', 'b'],
            'date' => '2026-11-05',
            'at' => '2026-11-05T10:30',
            'color' => '#ff0000',
        ]));

        $errors = $handler->validate($this->definition(), [
            'title' => 'F',
            'count' => 4,
            'agreed' => false,
            'dest' => ['c'],
            'date' => '05/11/2026',
            'at' => 'tomorrow',
            'color' => 'red',
        ]);
        $this->assertSame(['title', 'count', 'agreed', 'dest', 'date', 'at', 'color'], array_keys($errors));
        $this->assertSame(['This value should not be blank.'], $errors['agreed']);
        $this->assertSame(['title' => ['This value should not be blank.'], 'agreed' => ['This value should not be blank.']], $handler->validate($this->definition(), []));
    }
}
