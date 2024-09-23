<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine\Filter;

use Alchemy\RenditionFactory\Context\BuildHashes;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\Templating\TemplateResolverInterface;
use Alchemy\RenditionFactory\Transformer\BuildHashDiffInterface;
use Imagine\Image\AbstractFont;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;
use Liip\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;

class StampFilter implements LoaderInterface, BuildHashDiffInterface
{
    public function __construct(
        private TransformationContextInterface $context,
        private ImagineInterface $imagine,
        private TemplateResolverInterface $templateResolver,
        private string $fontDirectory = __DIR__.'/../../../../../fonts',
    ) {
    }

    public function load(ImageInterface $image, array $options = [])
    {
        $fontFile = $options['font'] ?? $this->fontDirectory.'/Roboto-Black.ttf';

        $position = $options['position'] ?? 'center';
        $angle = $options['angle'] ?? 0;
        $fontSize = $options['size'] ?? 16;

        $size = $image->getSize();

        /** @var AbstractFont $font */
        $font = $this->imagine->font(
            $fontFile,
            $fontSize,
            $image->palette()->color($options['color'] ?? '#000000', $options['alpha'] ?? 100)
        );

        $resolvedText = $this->templateResolver->resolve($options['text'], $this->context->getTemplatingContext());
        $buildHashes = $this->context->getBuildHashes();
        $buildHashes->setPath(BuildHashes::PATH_LEVEL_MODULE + 1, 'stamp');
        $buildHashes->addHash($this->hashText($resolvedText));

        $textSize = $font->box($resolvedText, $angle);

        switch ($position) {
            case 'topleft':
                $x = 0;
                $y = 0;
                break;
            case 'top':
                $x = ($size->getWidth() - $textSize->getWidth()) / 2;
                $y = 0;
                break;
            case 'topright':
                $x = $size->getWidth() - $textSize->getWidth();
                $y = 0;
                break;
            case 'left':
                $x = 0;
                $y = ($size->getHeight() - $textSize->getHeight()) / 2;
                break;
            case 'center':
                $x = ($size->getWidth() - $textSize->getWidth()) / 2;
                $y = ($size->getHeight() - $textSize->getHeight()) / 2;
                break;
            case 'right':
                $x = $size->getWidth() - $textSize->getWidth();
                $y = ($size->getHeight() - $textSize->getHeight()) / 2;
                break;
            case 'bottomleft':
                $x = 0;
                $y = $size->getHeight() - $textSize->getHeight();
                break;
            case 'bottom':
                $x = ($size->getWidth() - $textSize->getWidth()) / 2;
                $y = $size->getHeight() - $textSize->getHeight();
                break;
            case 'bottomright':
                $x = $size->getWidth() - $textSize->getWidth();
                $y = $size->getHeight() - $textSize->getHeight();
                break;
            case 'under':
                $x = ($size->getWidth() - $textSize->getWidth()) / 2;
                $y = $size->getHeight();

                $background = $image->palette()->color(
                    $options['background'] ?? '#FFFFFF',
                    $options['transparency'] ?? null
                );

                $canvas = $this->imagine->create(new Box(
                    $size->getWidth(),
                    $size->getHeight() + $textSize->getHeight()
                ), $background);

                $image = $canvas->paste($image, new Point(0, 0));

                break;
            case 'above':
                $x = ($size->getWidth() - $textSize->getWidth()) / 2;
                $y = 0;

                $background = $image->palette()->color(
                    $options['background'] ?? '#FFFFFF',
                    $options['transparency'] ?? null
                );

                $canvas = $this->imagine->create(new Box(
                    $size->getWidth(),
                    $size->getHeight() + $textSize->getHeight()
                ), $background);

                $image = $canvas->paste($image, new Point(0, $textSize->getHeight()));

                break;
            default:
                throw new \InvalidArgumentException("Unexpected position '{$options['position']}'");
        }

        $image->draw()
            ->text(
                $resolvedText,
                $font,
                new Point($x, $y),
                $angle,
                $options['width'] ?? null
            );

        return $image;
    }

    public function buildHashesDiffer(
        array $buildHashes,
        array $options,
        TransformationContextInterface $transformationContext
    ): bool {
        if (!empty($buildHashes)) {
            $textHash = array_shift($buildHashes);
            $resolvedText = $this->templateResolver->resolve($options['text'], $this->context->getTemplatingContext());
            if ($textHash !== $this->hashText($resolvedText)) {
                return true;
            }
        }

        return false;
    }

    private function hashText(string $text): string
    {
        return hash('sha256', $text);
    }
}
