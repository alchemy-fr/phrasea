<?php

declare(strict_types=1);

namespace App\Tests\Asset\Attribute;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\IpAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Notification\EntityDisableNotifyableException;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\Attribute\AttributeValueResolver;
use App\Service\Asset\Attribute\FallbackResolver;
use App\Service\Asset\Attribute\Index\AttributeIndex;
use App\Service\Asset\Attribute\TemplateResolver;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class FallbackResolverTest extends TestCase
{
    private const string WORKSPACE_ID = 'ce9899b7-2513-44b7-9c12-f6cf0f052b9f';
    private const array ENABLED_LOCALES = ['en', 'fr'];

    /**
     * @var array<string, AttributeDefinition> definitions of the workspace, indexed by slug
     */
    private array $definitions = [];

    protected function setUp(): void
    {
        $this->definitions = [];
    }

    public function testMonoValueFallbackIsResolvedFromFileMetadata(): void
    {
        $definition = $this->createDefinition('credit', [
            'fallback' => ['en' => '(c) {{ file.getMetadata("IPTC:Credit").value }}'],
        ]);
        $index = new AttributeIndex();

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile(['IPTC:Credit' => ['CoolMedia']])),
            'en',
            $definition,
            $index,
        );

        $this->assertCount(1, $attributes);
        $this->assertSame('(c) CoolMedia', $attributes[0]->getValue());
        $this->assertSame('en', $attributes[0]->getLocale());
        $this->assertSame(Attribute::ORIGIN_FALLBACK, $attributes[0]->getOrigin());
        $this->assertSame(0, $attributes[0]->getPosition());
        $this->assertFalse($attributes[0]->isInvalid());

        // the resolved attribute is indexed, so a second pass is a no-op
        $this->assertSame($attributes[0], $index->getAttribute($definition->getId(), 'en'));
    }

    public function testMultiValueFallbackCreatesOneAttributePerLine(): void
    {
        $definition = $this->createDefinition('keywords', [
            'multiple' => true,
            'fallback' => ['_' => "{{ file.getMetadata('IPTC:Keywords') }}"],
        ]);

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile(['IPTC:Keywords' => ['dog', 'cat', 'bird']])),
            '_',
            $definition,
            new AttributeIndex(),
        );

        $this->assertSame(['dog', 'cat', 'bird'], $this->values($attributes));
        $this->assertSame([0, 1, 2], array_map(fn (Attribute $a): int => $a->getPosition(), $attributes));
    }

    public function testMultiValueFallbackSkipsBlankLinesAndKeepsContiguousPositions(): void
    {
        $definition = $this->createDefinition('keywords', [
            'multiple' => true,
            'fallback' => ['_' => "first\n\n   \nsecond"],
        ]);

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile()),
            '_',
            $definition,
            new AttributeIndex(),
        );

        $this->assertSame(['first', 'second'], $this->values($attributes));
        $this->assertSame([0, 1], array_map(fn (Attribute $a): int => $a->getPosition(), $attributes));
    }

    public function testNothingIsResolvedWhenTheAttributeAlreadyHasAValue(): void
    {
        $definition = $this->createDefinition('credit', [
            'fallback' => ['en' => 'fallback credit'],
        ]);
        $asset = $this->createAsset($this->createFile());

        $index = new AttributeIndex();
        $existing = $this->createAttribute($definition, $asset, 'en', 'user credit');
        $index->addAttribute($existing);

        $attributes = $this->createResolver()->resolveAttrFallback($asset, 'en', $definition, $index);

        $this->assertSame([], $attributes);
        $this->assertSame($existing, $index->getAttribute($definition->getId(), 'en'));
    }

    public function testNothingIsResolvedWhenTheMultiValueAttributeAlreadyHasValues(): void
    {
        $definition = $this->createDefinition('keywords', [
            'multiple' => true,
            'fallback' => ['en' => "fallback\nkeywords"],
        ]);
        $asset = $this->createAsset($this->createFile());

        $index = new AttributeIndex();
        $index->addAttribute($this->createAttribute($definition, $asset, 'en', 'user keyword'));

        $attributes = $this->createResolver()->resolveAttrFallback($asset, 'en', $definition, $index);

        $this->assertSame([], $attributes);
        $this->assertSame(['user keyword'], $this->values($index->getAttributes($definition->getId(), 'en')));
    }

    public function testDisabledDefinitionIsSkipped(): void
    {
        $definition = $this->createDefinition('credit', [
            'enabled' => false,
            'fallback' => ['en' => 'fallback credit'],
        ]);

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile()),
            'en',
            $definition,
            new AttributeIndex(),
        );

        $this->assertSame([], $attributes);
    }

    public function testLocaleWithoutTemplateIsSkipped(): void
    {
        $definition = $this->createDefinition('credit', [
            'fallback' => ['en' => 'fallback credit'],
        ]);

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile()),
            'fr',
            $definition,
            new AttributeIndex(),
        );

        $this->assertSame([], $attributes);
    }

    public function testTemplateReadsAnAlreadyIndexedAttribute(): void
    {
        $title = $this->createDefinition('title');
        $definition = $this->createDefinition('warning', [
            'fallback' => ['en' => '{% if attr.title is empty %}Missing title{% else %}Title: {{ attr.title }}{% endif %}'],
        ]);
        $asset = $this->createAsset($this->createFile());

        $index = new AttributeIndex();
        $index->addAttribute($this->createAttribute($title, $asset, 'en', 'Hello'));

        $attributes = $this->createResolver()->resolveAttrFallback($asset, 'en', $definition, $index);

        $this->assertSame(['Title: Hello'], $this->values($attributes));
    }

    public function testTemplateResolvesAnUnsetMonoValueDependencyOnTheFly(): void
    {
        $credit = $this->createDefinition('credit', [
            'fallback' => ['en' => 'CoolMedia'],
        ]);
        $definition = $this->createDefinition('warning', [
            'fallback' => ['en' => 'Credit: {{ attr.credit }}'],
        ]);
        $index = new AttributeIndex();

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile()),
            'en',
            $definition,
            $index,
        );

        $this->assertSame(['Credit: CoolMedia'], $this->values($attributes));
        // the dependency has been resolved and indexed as well
        $this->assertSame('CoolMedia', $index->getAttribute($credit->getId(), 'en')?->getValue());
    }

    public function testTemplateResolvesAnUnsetMultiValueDependencyOnTheFly(): void
    {
        $this->createDefinition('keywords', [
            'multiple' => true,
            'fallback' => ['en' => "dog\ncat"],
        ]);
        $definition = $this->createDefinition('summary', [
            'fallback' => ['en' => '{{ attr.keywords|join(", ") }}'],
        ]);

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile()),
            'en',
            $definition,
            new AttributeIndex(),
        );

        $this->assertSame(['dog, cat'], $this->values($attributes));
    }

    public function testUnknownAttributeSlugResolvesToNull(): void
    {
        $definition = $this->createDefinition('warning', [
            'fallback' => ['en' => '[{{ attr.doesNotExist }}]'],
        ]);

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile()),
            'en',
            $definition,
            new AttributeIndex(),
        );

        $this->assertSame(['[]'], $this->values($attributes));
    }

    public function testCircularReferenceIsReported(): void
    {
        $a = $this->createDefinition('a', ['fallback' => ['en' => '{{ attr.b }}']]);
        $this->createDefinition('b', ['fallback' => ['en' => '{{ attr.a }}']]);

        try {
            $this->createResolver()->resolveAttrFallback(
                $this->createAsset($this->createFile()),
                'en',
                $a,
                new AttributeIndex(),
            );
            $this->fail(sprintf('Expected a %s to be thrown', EntityDisableNotifyableException::class));
        } catch (EntityDisableNotifyableException $e) {
            $this->assertSame('Error while resolving "a" (locale=en) attribute fallback value', $e->getSubject());
            $this->assertStringContainsString('Circular reference detected for attribute definition "a"', $e->getMessage());
        }
    }

    public function testInvalidValueIsRejectedWhenNotAllowed(): void
    {
        $definition = $this->createDefinition('serverIp', [
            'type' => IpAttributeType::NAME,
            'fallback' => ['en' => 'not-an-ip'],
        ]);

        try {
            $this->createResolver()->resolveAttrFallback(
                $this->createAsset($this->createFile()),
                'en',
                $definition,
                new AttributeIndex(),
            );
            $this->fail(sprintf('Expected a %s to be thrown', EntityDisableNotifyableException::class));
        } catch (EntityDisableNotifyableException $e) {
            $this->assertSame('Invalid value "not-an-ip" for "serverIp" (locale=en) attribute fallback value', $e->getSubject());
        }
    }

    public function testInvalidValueIsFlaggedWhenAllowed(): void
    {
        $definition = $this->createDefinition('serverIp', [
            'type' => IpAttributeType::NAME,
            'allowInvalid' => true,
            'fallback' => ['en' => 'not-an-ip'],
        ]);

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile()),
            'en',
            $definition,
            new AttributeIndex(),
        );

        $this->assertCount(1, $attributes);
        $this->assertSame('not-an-ip', $attributes[0]->getValue());
        $this->assertTrue($attributes[0]->isInvalid());
    }

    public function testTemplateDoesNotCrashWhenTheAssetHasNoFile(): void
    {
        $definition = $this->createDefinition('credit', [
            'fallback' => ['en' => '{{ file.getMetadata("IPTC:Credit").value ?? file.originalName ?? "unknown" }}'],
        ]);

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset(null),
            'en',
            $definition,
            new AttributeIndex(),
        );

        $this->assertSame(['unknown'], $this->values($attributes));
    }

    public function testTemplateReadsFilePropertiesThroughTheAccessorWrapper(): void
    {
        $definition = $this->createDefinition('source', [
            'fallback' => ['en' => '{{ file.originalName }} ({{ file.size }})'],
        ]);

        $attributes = $this->createResolver()->resolveAttrFallback(
            $this->createAsset($this->createFile()),
            'en',
            $definition,
            new AttributeIndex(),
        );

        $this->assertSame(['photo.jpg (1234)'], $this->values($attributes));
    }

    /**
     * @param Attribute[] $attributes
     *
     * @return string[]
     */
    private function values(array $attributes): array
    {
        return array_values(array_map(fn (Attribute $a): ?string => $a->getValue(), $attributes));
    }

    private function createResolver(): FallbackResolver
    {
        $repository = $this->createMock(AttributeDefinitionRepository::class);
        $repository->method('getWorkspaceDefinitions')
            ->willReturnCallback(fn (): array => array_values($this->definitions));

        $typeRegistry = $this->createMock(AttributeTypeRegistry::class);
        $typeRegistry->method('getType')
            ->willReturnCallback(static fn (string $name): AttributeTypeInterface => match ($name) {
                IpAttributeType::NAME => new IpAttributeType(),
                default => new TextAttributeType(),
            });

        return new FallbackResolver(new AttributeValueResolver(
            new TemplateResolver(),
            $typeRegistry,
            $repository,
        ));
    }

    /**
     * @param array{enabled?: bool, multiple?: bool, allowInvalid?: bool, type?: string, fallback?: array<string, string>} $options
     */
    private function createDefinition(string $slug, array $options = []): AttributeDefinition
    {
        $workspace = $this->createMock(Workspace::class);
        $workspace->method('getEnabledLocales')->willReturn(self::ENABLED_LOCALES);

        $definition = $this->createMock(AttributeDefinition::class);
        $definition->method('getId')->willReturn(Uuid::uuid4()->toString());
        $definition->method('getSlug')->willReturn($slug);
        $definition->method('getName')->willReturn($slug);
        $definition->method('getPosition')->willReturn(count($this->definitions));
        $definition->method('getWorkspace')->willReturn($workspace);
        $definition->method('getWorkspaceId')->willReturn(self::WORKSPACE_ID);
        $definition->method('isEnabled')->willReturn($options['enabled'] ?? true);
        $definition->method('isMultiple')->willReturn($options['multiple'] ?? false);
        $definition->method('isAllowInvalid')->willReturn($options['allowInvalid'] ?? false);
        $definition->method('getType')->willReturn($options['type'] ?? TextAttributeType::NAME);
        $definition->method('getFallback')->willReturn($options['fallback'] ?? null);

        return $this->definitions[$slug] = $definition;
    }

    private function createAsset(?File $file): Asset
    {
        $asset = $this->createMock(Asset::class);
        $asset->method('getId')->willReturn(Uuid::uuid4()->toString());
        $asset->method('getWorkspaceId')->willReturn(self::WORKSPACE_ID);
        $asset->method('getSource')->willReturn($file);

        return $asset;
    }

    /**
     * @param array<string, string[]> $metadata indexed by "Group:Tag"
     */
    private function createFile(array $metadata = []): File
    {
        $file = $this->createMock(File::class);
        $file->method('getOriginalName')->willReturn('photo.jpg');
        $file->method('getSize')->willReturn(1234);
        $file->method('getMetadataNameValues')
            ->willReturnCallback(static fn (string $name): ?array => $metadata[$name] ?? null);

        return $file;
    }

    private function createAttribute(
        AttributeDefinition $definition,
        Asset $asset,
        string $locale,
        string $value,
    ): Attribute {
        $attribute = new Attribute();
        $attribute->setDefinition($definition);
        $attribute->setAsset($asset);
        $attribute->setLocale($locale);
        $attribute->setValue($value);
        $attribute->setOrigin(Attribute::ORIGIN_HUMAN);

        return $attribute;
    }
}
