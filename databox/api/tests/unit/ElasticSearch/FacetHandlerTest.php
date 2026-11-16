<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch;

use App\Elasticsearch\BuiltInAttribute\BuiltInAttributeRegistry;
use App\Elasticsearch\FacetHandler;
use App\Tests\Unit\Attribute\Type\AttributeTypeRegistryTestFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Translation\TranslatorInterface;

class FacetHandlerTest extends TestCase
{
    public function testFilteredFacetsKeepTheFormatOfPlainOnes(): void
    {
        $handler = new FacetHandler(
            new BuiltInAttributeRegistry(new ServiceLocator([])),
            AttributeTypeRegistryTestFactory::create(),
            $this->createStub(TranslatorInterface::class),
        );

        $terms = [
            'meta' => ['displayName' => 'Salary'],
            'doc_count_error_upper_bound' => 0,
            'sum_other_doc_count' => 0,
            'buckets' => [['key' => '1000', 'doc_count' => 2]],
        ];

        $plain = $handler->normalizeBuckets([
            'salary_text_s' => $terms,
            'salary_text_s'.FacetHandler::MISSING_SUFFIX => ['doc_count' => 3],
        ]);
        $filtered = $handler->normalizeBuckets([
            'salary_text_s' => [
                'doc_count' => 5,
                FacetHandler::VISIBLE_AGG => $terms,
            ],
            'salary_text_s'.FacetHandler::MISSING_SUFFIX => [
                'doc_count' => 5,
                FacetHandler::VISIBLE_AGG => ['doc_count' => 3],
            ],
        ]);

        $this->assertSame([
            'salary_text_s' => [
                ...$terms,
                'missing_count' => 3,
            ],
        ], $plain);
        $this->assertSame($plain, $filtered);
    }
}
