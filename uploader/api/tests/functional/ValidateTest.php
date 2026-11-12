<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Form\FormValidator;
use PHPUnit\Framework\Attributes\DataProvider;

class ValidateTest extends AbstractUploaderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $target = $this->getOrCreateDefaultTarget();
        $target->setForm([
            'version' => 1,
            'workspaceId' => null,
            'fields' => [
                ['id' => 'f_album', 'source' => 'custom', 'key' => 'album', 'type' => 'text', 'required' => true, 'label' => ['_' => 'Album']],
                [
                    'id' => 'f_dest',
                    'source' => 'custom',
                    'key' => 'dest',
                    'type' => 'choice',
                    'widget' => 'select',
                    'choices' => [
                        ['value' => 'One', 'label' => ['_' => 'One']],
                        ['value' => 'Two', 'label' => ['_' => 'Two']],
                    ],
                    'label' => ['_' => 'Choose a destination'],
                ],
                ['id' => 'f_description', 'source' => 'custom', 'key' => 'description', 'type' => 'text', 'label' => ['_' => 'Description']],
                ['id' => 'f_agreed', 'source' => 'custom', 'key' => 'agreed', 'type' => 'boolean', 'required' => true, 'label' => ['_' => 'I agree with the terms']],
            ],
        ]);
        $em = self::getEntityManager();
        $em->persist($target);
        $em->flush();
    }

    public function testValidateOK(): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/form/validate', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => [
                'album' => 'Foo',
                'agreed' => true,
            ],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(['errors' => []], $json);
    }

    public function testValidateWithAnonymousUser(): void
    {
        $response = $this->request(null, 'POST', '/form/validate', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => [
                'album' => 'Foo',
                'agreed' => true,
            ],
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testValidateWithoutForm(): void
    {
        $target = $this->getOrCreateDefaultTarget();
        $target->setForm(null);
        $em = self::getEntityManager();
        $em->persist($target);
        $em->flush();

        $this->assertSame(['_form' => [FormValidator::NO_FORM_ERROR]], $this->validate(['album' => 'Foo']));
        // The keys handled by the uploader itself are always accepted
        $this->assertSame([], $this->validate(['__notify' => true]));
        $this->assertSame([], $this->validate([]));
    }

    #[DataProvider('formDataProvider')]
    public function testValidateGivesErrors(array $data, array $exceptedErrors): void
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/form/validate', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => $data,
        ]);
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['errors' => $exceptedErrors], $json);
    }

    public static function formDataProvider(): array
    {
        return [
            [[
                'album' => 'Foo',
                'agreed' => true,
            ], []],

            [[
                'album' => '',
                'agreed' => true,
            ], [
                'album' => ['This value should not be blank.'],
            ]],

            [[
                'album' => 'Foo',
                'dest' => 'Three',
                'agreed' => true,
            ], [
                'dest' => ['The value you selected is not a valid choice.'],
            ]],

            [[
                'album' => '',
                'agreed' => false,
            ], [
                'album' => ['This value should not be blank.'],
                'agreed' => ['This value should not be blank.'],
            ]],

            [[
            ], [
                'album' => ['This value should not be blank.'],
                'agreed' => ['This value should not be blank.'],
            ]],
        ];
    }

    private function validate(array $data): array
    {
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/form/validate', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => $data,
        ]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));

        return $response->toArray()['errors'];
    }
}
