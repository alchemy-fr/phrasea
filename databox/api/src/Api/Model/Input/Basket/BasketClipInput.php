<?php

declare(strict_types=1);

namespace App\Api\Model\Input\Basket;

use Symfony\Component\Validator\Constraints as Assert;

class BasketClipInput
{
    #[Assert\Valid]
    public ?ClipTimeRangeInput $timeRange = null;

    #[Assert\Valid]
    public ?ClipCropInput $crop = null;

    public function toArray(): array
    {
        $data = [];
        if (null !== $this->timeRange) {
            $data['timeRange'] = $this->timeRange->toArray();
        }
        if (null !== $this->crop) {
            $data['crop'] = $this->crop->toArray();
        }

        return $data;
    }
}
