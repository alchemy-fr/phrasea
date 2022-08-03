<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class ValidateTest extends AbstractUploaderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/form-schemas', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => json_decode(file_get_contents(__DIR__.'/fixtures/liform-schema.json'), true),
        ]);
    }

    public function testValidateOK(): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/form/validate', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => [
                'album' => 'Foo',
                'agreed' => true,
            ],
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
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

    /**
     * @dataProvider formDataProvider
     */
    public function testValidateGivesErrors(array $data, array $exceptedErrors): void
    {
        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'POST', '/form/validate', [
            'target' => '/targets/'.$this->getOrCreateDefaultTarget()->getId(),
            'data' => $data,
        ]);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['errors' => $exceptedErrors], $json);
    }

    public function formDataProvider(): array
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
}
