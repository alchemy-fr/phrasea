<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\HtmlAttributeType;

class HtmlAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new HtmlAttributeType(new \HTMLPurifier());
    }

    public function getNormalizationCases(): array
    {
        return $this->getPurifyCases();
    }

    public function getDenormalizationCases(): array
    {
        return $this->getPurifyCases();
    }

    private function getPurifyCases(): array
    {
        return [
            [null, null],
            ['null', 'null'],
            ['', ''],
            ['<a>link</a>', '<a>link</a>'],
            ['<img onclick="alert()" src="https://foo/img.jpg">', '<img src="https://foo/img.jpg" alt="img.jpg" />'],
            ['<br/>', '<br />'],
            ['<script>alert("ok")</script>', ''],
        ];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [
            [null, null],
            ['null', 'null'],
            ['', ''],
            ['<a>link</a>', 'link'],
            ['<br/>', ''],
        ];
    }
}
