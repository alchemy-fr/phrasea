<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\HtmlAttributeType;

class HtmlAttributeTypeTest extends TextAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new HtmlAttributeType(new \HTMLPurifier());
    }

    public function getNormalizationCases(): array
    {
        return [
            ...parent::getNormalizationCases(),
            ['<a>link</a>', '<a>link</a>'],
            ['<img onclick="alert()" src="https://foo/img.jpg">', '<img src="https://foo/img.jpg" alt="img.jpg" />'],
            ['<br/>', '<br />'],
            ['<script>alert("ok")</script>', null],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            ['<a>link</a>', '<a>link</a>'],
            ['<img onclick="alert()" src="https://foo/img.jpg">', '<img src="https://foo/img.jpg" alt="img.jpg" />'],
            ['<br/>', '<br />'],
            ['<script>alert("ok")</script>', ''],
        ];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [
            ...parent::getElasticsearchNormalizationCases(),
            ['<a>link</a>', 'link'],
            ['<br/>', ''],
        ];
    }
}
