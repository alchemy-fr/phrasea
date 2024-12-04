<?php

namespace Alchemy\RenditionFactory\Transformer\Video;

use Alchemy\RenditionFactory\Config\ModuleOptionsResolver;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\Transformer\Video\Format\FormatInterface;
use FFMpeg;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ModuleCommonArgs
{
    private FormatInterface $outputFormat;
    private string $extension;
    private FFMpeg\FFMpeg $ffmpeg;

    public function __construct(
        ServiceLocator $formats,
        array $options,
        TransformationContextInterface $context,
        ModuleOptionsResolver $optionsResolver)
    {
        $resolverContext = $context->getTemplatingContext();

        $format = $optionsResolver->resolveOption($options['format'] ?? null, $resolverContext);
        if (!$format) {
            throw new \InvalidArgumentException('Missing format');
        }
        if (!$formats->has($format)) {
            throw new \InvalidArgumentException(sprintf('Invalid format %s', $format));
        }

        $this->outputFormat = $formats->get($format);

        $extension = $optionsResolver->resolveOption($options['extension'] ?? null, $resolverContext);
        if (null !== $extension) {
            if (!in_array($extension, $this->outputFormat->getAllowedExtensions())) {
                throw new \InvalidArgumentException(sprintf('Invalid extension %s for format %s', $extension, $format));
            }
        } else {
            $extension = $this->outputFormat->getAllowedExtensions()[0];
        }
        $this->extension = $extension;

        $ffmpegOptions = [
            'timeout' => $optionsResolver->resolveOption($options['timeout'] ?? null, $resolverContext),
            'threads' => $optionsResolver->resolveOption($options['threads'] ?? null, $resolverContext),
        ];
        $this->ffmpeg = FFMpegHelper::createFFMpeg($ffmpegOptions);
    }

    public function getOutputFormat(): FormatInterface
    {
        return $this->outputFormat;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getFFMpeg(): FFMpeg\FFMpeg
    {
        return $this->ffmpeg;
    }
}
