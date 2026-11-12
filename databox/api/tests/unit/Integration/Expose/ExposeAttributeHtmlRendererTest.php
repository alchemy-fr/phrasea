<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Expose;

use App\Attribute\AttributeInterface;
use App\Attribute\Type\HtmlAttributeType;
use App\Attribute\Type\JsonAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\AttributeDefinition;
use App\Integration\Phrasea\Expose\ExposeAttributeHtmlRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExposeAttributeHtmlRendererTest extends TestCase
{
    public function testRenderEscapesTheTextValues(): void
    {
        $html = $this->render($this->createDefinition(), 'a <b>bold</b> & co');

        $this->assertSame('a &lt;b&gt;bold&lt;/b&gt; &amp; co', $this->getValue($html));
    }

    public function testRenderKeepsTheHtmlValues(): void
    {
        $html = $this->render($this->createDefinition(type: HtmlAttributeType::NAME), '<b>x</b>');

        $this->assertSame('<b>x</b>', $this->getValue($html));
    }

    public function testRenderEscapesTheCodeValues(): void
    {
        $html = $this->render($this->createDefinition(type: JsonAttributeType::NAME), '{"a": "<b>"}');

        $this->assertSame('{&quot;a&quot;: &quot;&lt;b&gt;&quot;}', $this->getValue($html));
    }

    public function testRenderEscapesEachOfTheMultipleValues(): void
    {
        $html = $this->render($this->createDefinition(multiple: true), ['a<b', null, 'c']);

        $this->assertSame('a&lt;b, c', $this->getValue($html));
    }

    public function testRenderEscapesTheNameTypeAndSlug(): void
    {
        $html = $this->render($this->createDefinition(name: 'Size <cm>', type: 'te"xt', slug: 'si"ze'), 'v');

        $this->assertStringContainsString('class="attribute-name attribute-name-type-te&quot;xt attribute-name-si&quot;ze">Size &lt;cm&gt;</div>', $html);
    }

    public function testRenderUsesTheNameTranslatedInTheLocale(): void
    {
        $definition = $this->createDefinition(name: 'Title <en>');
        $definition->setTranslations(['name' => ['fr' => 'Titre <fr>', 'de' => 'Titel']]);

        $html = $this->render($definition, 'v', 'fr');

        $this->assertStringContainsString('>Titre &lt;fr&gt;</div>', $html);
        $this->assertStringContainsString(' lang="fr">v</div>', $html);
    }

    #[DataProvider('provideEmptyValues')]
    public function testRenderReturnsNullOnEmptyValue(string|array|null $value): void
    {
        $this->assertNull(new ExposeAttributeHtmlRenderer()->render($this->createDefinition(), $value));
    }

    public static function provideEmptyValues(): iterable
    {
        yield 'empty string' => [''];
        yield 'null' => [null];
        yield 'empty array' => [[]];
    }

    #[DataProvider('provideLocalesWithoutLang')]
    public function testRenderOmitsTheLangOfNoLocale(?string $locale): void
    {
        $definition = $this->createDefinition();
        $definition->setTranslations(['name' => ['fr' => 'Titre']]);

        $html = $this->render($definition, 'v', $locale);

        $this->assertStringNotContainsString('lang=', $html);
        $this->assertStringContainsString('>Title</div>', $html);
    }

    public static function provideLocalesWithoutLang(): iterable
    {
        yield 'no locale' => [null];
        yield 'NO_LOCALE' => [AttributeInterface::NO_LOCALE];
    }

    private function render(AttributeDefinition $definition, string|array|null $value, ?string $locale = null): string
    {
        $html = new ExposeAttributeHtmlRenderer()->render($definition, $value, $locale);
        $this->assertNotNull($html);

        return $html;
    }

    private function getValue(string $html): string
    {
        $this->assertSame(1, preg_match('#<div class="attribute-value [^"]*"[^>]*>(.*?)</div>#s', $html, $matches));

        return $matches[1];
    }

    private function createDefinition(
        string $name = 'Title',
        string $type = TextAttributeType::NAME,
        string $slug = 'title',
        bool $multiple = false,
    ): AttributeDefinition {
        $definition = new AttributeDefinition();
        $definition->setName($name);
        $definition->setType($type);
        $definition->setSlug($slug);
        $definition->setMultiple($multiple);

        return $definition;
    }
}
