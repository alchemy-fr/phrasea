<?php

namespace Alchemy\RenditionFactory\Transformer\Image\Imagine\Filter;

use Imagine\Image\AbstractFont;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;
use Liip\ImagineBundle\Imagine\Filter\Loader\LoaderInterface;

class StampFilter implements LoaderInterface
{
    public function __construct(
        private ImagineInterface $imagine,
        private string $fontDirectory = __DIR__.'/../../../../../fonts'
    )
    {
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

        $textSize = $font->box($options['text'], $angle);

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
            case 'hover':
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
                $options['text'],
                $font,
                new Point($x, $y),
                $angle,
                $options['width'] ?? null
            );

        return $image;
    }
}
