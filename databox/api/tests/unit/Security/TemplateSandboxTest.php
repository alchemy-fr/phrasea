<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\File\FileMetadataAccessorWrapper;
use App\Service\Asset\Attribute\TemplateResolver;
use App\Validator\TwigConstraint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Twig\Error\Error;

/**
 * Attribute initial values/fallbacks and rendition options are Twig templates written by
 * workspace editors and rendered by the workers: they must not run code nor change data.
 */
class TemplateSandboxTest extends TestCase
{
    /**
     * @dataProvider getLegitTemplates
     */
    public function testLegitTemplatesAreRendered(string $template, string $expected): void
    {
        $this->assertSame($expected, new TemplateResolver()->resolve($template, $this->createValues()));
    }

    public function getLegitTemplates(): array
    {
        return [
            ['{{ asset.ownerId }}', 'owner'],
            ['{{ file.originalName }} ({{ file.size }})', 'photo.jpg (42)'],
            ['{{ file.metadata("IPTC:City").value ?? "no-city" }}', 'no-city'],
            ['{% for kw in ["a", "b"] %}{{ kw|upper }}{% endfor %}', 'AB'],
            ['{{ ["a", "b"]|merge(["c"])|join(" ; ") }}', 'a ; b ; c'],
            ['{% if asset.ownerId is empty %}none{% else %}{{ asset.ownerId|lower }}{% endif %}', 'owner'],
            ['{{ [3, 1, 2]|filter(v => v > 1)|join(",") }}', '3,2'],
        ];
    }

    /**
     * @dataProvider getMaliciousTemplates
     */
    public function testMaliciousTemplatesAreRejected(string $template): void
    {
        $values = $this->createValues();

        try {
            new TemplateResolver()->resolve($template, $values);
            $this->fail(sprintf('Template "%s" should have been rejected', $template));
        } catch (Error) {
            $this->assertSame('owner', $values['asset']->getOwnerId());
            $this->assertSame('/files/photo.jpg', $values['rawFile']->getPath());
        }
    }

    public function getMaliciousTemplates(): array
    {
        return [
            'command through filter' => ['{{ ["id"]|filter("system")|join }}'],
            'command through map' => ['{{ ["id"]|map("system")|join }}'],
            'command through sort' => ['{{ ["id", "x"]|sort("system")|join }}'],
            'command through reduce' => ['{{ ["id"]|reduce("system")|join }}'],
            'entity setter' => ['{{ asset.setOwnerId("attacker") }}'],
            'entity setter by attribute' => ['{{ attribute(asset, "setOwnerId", ["attacker"]) }}'],
            'file setter through the wrapper' => ['{{ file.setPath("/etc/passwd") }}'],
            'file setter through the raw entity' => ['{{ rawFile.setPath("/etc/passwd") }}'],
            'include' => ['{{ include("/etc/passwd") }}'],
            'source' => ['{{ source("/etc/passwd") }}'],
            'constant' => ['{{ constant("PHP_VERSION") }}'],
            'macro' => ['{% macro x() %}{% endmacro %}'],
        ];
    }

    public function testTemplatesAreValidatedAgainstTheSandbox(): void
    {
        $validator = Validation::createValidator();

        $this->assertCount(0, $validator->validate('{{ file.originalName|upper }}', new TwigConstraint()));
        $this->assertCount(1, $validator->validate('{{ include("x") }}', new TwigConstraint()));
        $this->assertCount(1, $validator->validate('{% macro x() %}{% endmacro %}', new TwigConstraint()));
        $this->assertCount(1, $validator->validate('{{ unclosed', new TwigConstraint()));
    }

    private function createValues(): array
    {
        $file = new File();
        $file->setPath('/files/photo.jpg');
        $file->setOriginalName('photo.jpg');
        $file->setSize(42);

        $asset = new Asset();
        $asset->setOwnerId('owner');
        $asset->setSource($file);

        return [
            'asset' => $asset,
            'file' => new FileMetadataAccessorWrapper($file),
            'rawFile' => $file,
        ];
    }
}
