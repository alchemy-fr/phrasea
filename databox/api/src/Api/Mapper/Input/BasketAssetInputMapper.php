<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\Basket\BasketAssetInput;
use App\Api\Model\Input\MediaClipInput;
use App\Entity\Basket\BasketAsset;
use App\Model\MediaClip;
use App\Service\Clip\ClipFamily;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
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
    public static function applyClip(BasketAsset $basketAsset, ?MediaClipInput $input): void
    {
        $clip = MediaClip::fromArray($input?->toArray());
        if (null !== $clip) {
            ClipFamily::assertApplicable($clip, $basketAsset->getAsset()->getSource()?->getType());
        }

        $basketAsset->setClip($clip?->toArray());
    }
}
