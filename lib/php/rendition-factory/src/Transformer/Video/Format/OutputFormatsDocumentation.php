<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

class OutputFormatsDocumentation
{
    public function __construct(
        #[TaggedLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')]
        private readonly ServiceLocator $formats,
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

        $text = "### Supported output `format`s.\n";
        $text .= "| Family | Format | Mime type | Extensions |\n";
        $text .= "|-|-|-|-|\n";
        foreach ($formats as $familyFormats) {
            $text .= sprintf("| %s ||||\n",
                $familyFormats[0]->getFamily()->value,
            );
            foreach ($familyFormats as $format) {
                $text .= sprintf("|| %s | %s | %s |\n",
                    $format->getFormat(),
                    $format->getMimeType(),
                    implode(', ', $format->getAllowedExtensions())
                );
            }
        }

        return $text;
    }
}
