<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\Basket\BasketAssetInput;
use App\Api\Model\Input\Basket\BasketClipInput;
use App\Entity\Basket\BasketAsset;
use App\Model\BasketClip;
use App\Service\Clip\ClipFamily;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsTaggedItem(index: BasketAssetInput::class)]
class BasketAssetInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    /**
     * @param BasketAssetInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        if (!$target instanceof BasketAsset) {
            throw new BadRequestHttpException('Basket items are created by adding assets to a basket');
        }

        if (self::hasKey('clip', $data->clip, $context)) {
            self::applyClip($target, $data->clip);
        }

        return $target;
    }

    /**
     * Sets (or removes, with null or an empty clip) the clip of a basket item.
     *
     * @throws BadRequestHttpException when the clip does not apply to the asset source
     */
    public static function applyClip(BasketAsset $basketAsset, ?BasketClipInput $input): void
    {
        $clip = BasketClip::fromArray($input?->toArray());
        if (null !== $clip) {
            $mimeType = $basketAsset->getAsset()->getSource()?->getType();
            if (null === $mimeType) {
                throw new BadRequestHttpException('The asset has no source file to clip');
            }
            if ($clip->hasTimeRange() && !ClipFamily::acceptsTimeRange($mimeType)) {
                throw new BadRequestHttpException(sprintf('A time range only applies to a video or an audio file, not to "%s"', $mimeType));
            }
            if ($clip->hasCrop() && !ClipFamily::acceptsCrop($mimeType)) {
                throw new BadRequestHttpException(sprintf('A crop only applies to an image or a video file, not to "%s"', $mimeType));
            }
        }

        $basketAsset->setClip($clip?->toArray());
    }

    /**
     * Whether the key was sent, null included (a merge patch removes a value with null).
     */
    private static function hasKey(string $key, mixed $value, array $context): bool
    {
        $request = $context['request'] ?? null;
        if (!$request instanceof Request) {
            return null !== $value;
        }

        try {
            return array_key_exists($key, $request->toArray());
        } catch (JsonException) {
            return null !== $value;
        }
    }
}
