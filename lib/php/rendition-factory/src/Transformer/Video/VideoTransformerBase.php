<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Config\ModuleOptionsResolver;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format\FormatInterface;
use FFMpeg;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

readonly class VideoTransformerBase
{
    protected ?string $format;
    protected FormatInterface $outputFormat;
    protected ?string $extension;
    protected TransformationContextInterface $context;
    protected FFMpeg\FFMpeg $ffmpeg;

    public function __construct(#[AutowireLocator(FormatInterface::TAG, defaultIndexMethod: 'getFormat')] protected ServiceLocator $formats,
        protected ModuleOptionsResolver $optionsResolver,
    ) {
    }

    protected function prepare(array $options, TransformationContextInterface $context): void
    {
        $this->context = $context;

        $resolverContext = [
            'metadata' => $context->getTemplatingContext(),
        ];

        $format = $this->optionsResolver->resolveOption($options['format'] ?? null, $resolverContext);
        if (!$format) {
            throw new \InvalidArgumentException('Missing format');
        }
        if (!$this->formats->has($format)) {
            throw new \InvalidArgumentException(sprintf('Invalid format %s', $format));
        }
        $this->format = $format;

        $this->outputFormat = $this->formats->get($format);

        $extension = $this->optionsResolver->resolveOption($options['extension'] ?? null, $resolverContext);
        if (null !== $extension) {
            if (!in_array($extension, $this->outputFormat->getAllowedExtensions())) {
                throw new \InvalidArgumentException(sprintf('Invalid extension %s for format %s', $extension, $format));
            }
        } else {
            $extension = $this->outputFormat->getAllowedExtensions()[0];
        }
        $this->extension = $extension;

        $ffmpegOptions = [
            'timeout' => $this->optionsResolver->resolveOption($options['timeout'] ?? null, $resolverContext),
            'threads' => $this->optionsResolver->resolveOption($options['threads'] ?? null, $resolverContext),
            'logger' => $context->getLogger(),
        ];
        $this->ffmpeg = FFMpegHelper::createFFMpeg($ffmpegOptions);
    }

    protected function log(string $message): void
    {
        $this->context->log($message);
    }
}
