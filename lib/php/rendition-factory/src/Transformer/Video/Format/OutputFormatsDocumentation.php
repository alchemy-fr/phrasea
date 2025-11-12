<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class OutputFormatsDocumentation
{
    public function __construct(
        #[TaggedLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')]
        private ServiceLocator $formats,
    ) {
    }

    public function listFormats(array $supportedFormats): string
    {
        $formats = [];
        foreach ($this->formats->getProvidedServices() as $formatName => $formatFqcn) {
            if (!in_array($formatName, $supportedFormats)) {
                continue;
            }
            /** @var FormatInterface $format */
            $format = $this->formats->get($formatName);
            $family = $format->getFamily()->value;
            if (!array_key_exists($family, $formats)) {
                $formats[$family] = [];
            }
            $formats[$family][] = $format;
        }
        ksort($formats);

        $text = "#### Supported output formats.\n";
        $text .= "| Family | Format | Mime type | Extensions |\n";
        $text .= "|-|-|-|-|\n";
        $lastFamily = null;
        foreach ($formats as $familyFormats) {
            foreach ($familyFormats as $format) {
                if (null !== $lastFamily && $lastFamily !== $format->getFamily()->value) {
                    $text .= "|-|-|-|-|\n";
                }
                $lastFamily = $format->getFamily()->value;
                $text .= sprintf("| %s | %s | %s | %s |\n",
                    $format->getFamily()->value,
                    $format->getFormat(),
                    $format->getMimeType(),
                    implode(', ', $format->getAllowedExtensions())
                );
            }
        }

        return $text;
    }
}
