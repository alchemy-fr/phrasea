<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\AbstractDataboxTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CrudTest extends AbstractDataboxTestCase
{
    /**
     * @dataProvider getCases
     */
    public function testCrud(
        string $method,
        string $uri,
        ?string $userId = null,
        ?array $data = null,
        array $expectations = [],
        array $options = [],
    ): void {
        if ($options['createItem'] ?? false) {
            $response = $this->testCase(...$options['createItem']);
            $options['itemId'] = $response->toArray()['id'];
        }

        $this->testCase($method, $uri, $userId, $data, $expectations, $options);
    }

    private function testCase(
        string $method,
        string $uri,
        ?string $userId = null,
        ?array $data = null,
        array $expectations = [],
        array $options = [],
    ): ResponseInterface {
        $workspace = $this->getOrCreateDefaultWorkspace();
        $attributePolicy = $this->getOrCreateDefaultAttributePolicy();

        $replacePH = fn (mixed $s): mixed => $this->replacePlaceholders($s, [
            'workspace' => $workspace,
            'attributePolicy' => $attributePolicy,
            'lastId' => $options['itemId'] ?? 'Undefined Item ID',
        ]);

        $expectedStatusCode = $expectations['code'] ?? match ($method) {
            'POST' => 201,
            'DELETE' => 204,
            default => 200,
        };
        unset($expectations['code']);

        $defaultOptions = [];

        if (null !== $userId) {
            $defaultOptions['headers'] ??= [];
            $defaultOptions['headers']['Authorization'] = 'Bearer '.KeycloakClientTestMock::getJwtFor($userId);
        }

        if (null !== $data) {
            $defaultOptions['json'] = $data;
        }

        $httpOptions = $replacePH(array_merge_recursive($defaultOptions, $options['request'] ?? []));

        $client = self::createClient();
        $response = $client->request($method, $replacePH($uri), $httpOptions);

        $this->assertResponseStatusCodeSame($expectedStatusCode);

        foreach ($expectations as $expectation) {
            // TODO
        }

        return $response;
    }

    private function replacePlaceholders(mixed $input, array $context): mixed
    {
        if (!empty($input) && is_string($input)) {
            return str_replace([
                '{workspaceId}',
                '{lastId}',
                '{attributePolicyId}',
            ], [
                $context['workspace']->getId(),
                $context['lastId'] ?? 'undefinedLastId',
                $context['attributePolicy']->getId(),
            ], $input);
        }

        if (is_array($input)) {
            return array_map(fn ($s) => $this->replacePlaceholders($s, $context), $input);
        }

        return $input;
    }

    public function getCases(): array
    {
        $createAttributePolicy = ['POST', '/attribute-policies', KeycloakClientTestMock::ADMIN_UID, [
            'workspace' => '/workspaces/{workspaceId}',
            'name' => 'AttrClass Test',
            'public' => true,
            'editable' => false,
        ]];

        $createRenditionPolicy = ['POST', '/rendition-policies', KeycloakClientTestMock::ADMIN_UID, [
            'workspace' => '/workspaces/{workspaceId}',
            'name' => 'RendClass Test',
            'public' => true,
        ]];

        $createAttributeDefinition = ['POST', '/attribute-definitions', KeycloakClientTestMock::ADMIN_UID, [
            'workspace' => '/workspaces/{workspaceId}',
            'name' => 'AttrDef Test',
            'policy' => '/attribute-policies/{attributePolicyId}',
        ]];

        return [
            // AttributePolicy
            ['POST', '/attribute-policies', null, [], [
                'code' => 401,
            ]],

            ['POST', '/attribute-policies', KeycloakClientTestMock::USER_UID, [
                'workspace' => '/workspaces/{workspaceId}',
            ], [
                'code' => 403,
            ]],

            ['POST', '/attribute-policies', KeycloakClientTestMock::ADMIN_UID, [
                'workspace' => '/workspaces/{workspaceId}',
            ], [
                'code' => 422,
            ]],

            ['POST', '/attribute-policies', KeycloakClientTestMock::USER_UID, [
                'workspace' => '/workspaces/{workspaceId}',
                'name' => 'AttrClass Test',
                'public' => true,
                'editable' => false,
            ], [
                'code' => 403,
            ]],

            $createAttributePolicy,

            ['PUT', '/attribute-policies/{lastId}', KeycloakClientTestMock::ADMIN_UID, [
                'name' => 'AttrClass Test 2',
                'public' => false,
                'editable' => true,
            ], [], [
                'createItem' => $createAttributePolicy,
            ]],

            // RenditionPolicy
            ['POST', '/rendition-policies', null, [], [
                'code' => 401,
            ]],

            ['POST', '/rendition-policies', KeycloakClientTestMock::USER_UID, [
            ], [
                'code' => 403,
            ]],

            ['POST', '/rendition-policies', KeycloakClientTestMock::ADMIN_UID, [
                'workspace' => '/workspaces/{workspaceId}',
            ], [
                'code' => 422,
            ]],

            ['POST', '/rendition-policies', KeycloakClientTestMock::USER_UID, [
                'workspace' => '/workspaces/{workspaceId}',
                'name' => 'RendClass Test',
                'public' => true,
            ], [
                'code' => 403,
            ]],

            $createRenditionPolicy,

            ['PUT', '/rendition-policies/{lastId}', KeycloakClientTestMock::ADMIN_UID, [
                'name' => 'RendClass Test 2',
                'public' => false,
            ], [], [
                'createItem' => $createRenditionPolicy,
            ]],

            // AttributeDefinition
            ['POST', '/attribute-definitions', null, [], [
                'code' => 401,
            ]],

            ['POST', '/attribute-definitions', KeycloakClientTestMock::USER_UID, [
            ], [
                'code' => 400,
            ]],

            ['POST', '/attribute-definitions', KeycloakClientTestMock::USER_UID, [
                'workspace' => '/workspaces/{workspaceId}',
            ], [
                'code' => 403,
            ]],

            ['POST', '/attribute-definitions', KeycloakClientTestMock::ADMIN_UID, [
                'workspace' => '/workspaces/{workspaceId}',
            ], [
                'code' => 422,
            ]],

            ['POST', '/attribute-definitions', KeycloakClientTestMock::USER_UID, [
                'workspace' => '/workspaces/{workspaceId}',
                'name' => 'AttrClass Test',
                'policy' => '/attribute-policies/{attributePolicyId}',
            ], [
                'code' => 403,
            ]],

            $createAttributeDefinition,

            ['PUT', '/attribute-definitions/{lastId}', KeycloakClientTestMock::ADMIN_UID, [
                'name' => 'AttrDef Test 2',
            ], [], [
                'createItem' => $createAttributeDefinition,
            ]],
        ];
    }
}
