<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Model\MediaClip;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Time ranges and/or crop: region of interest of an asset, clip of a basket item.
 */
class MediaClipInput
{
    /**
     * Time ranges, in seconds (video, audio). They are sorted and merged when they overlap.
     *
     * @var ClipTimeRangeInput[]|null
     */
    #[Assert\Valid]
    #[Assert\Count(max: MediaClip::MAX_TIME_RANGES)]
    public ?array $timeRanges = null;

    /**
     * A single time range, accepted for compatibility: use "timeRanges".
     */
    #[Assert\Valid]
    public ?ClipTimeRangeInput $timeRange = null;

    #[Assert\Valid]
    public ?ClipCropInput $crop = null;

    public function toArray(): array
    {
        $ranges = array_map(static fn (ClipTimeRangeInput $range): array => $range->toArray(), $this->timeRanges ?? []);
        if (null !== $this->timeRange) {
            $ranges[] = $this->timeRange->toArray();
        }

        $data = [];
        if ([] !== $ranges) {
            $data['timeRanges'] = $ranges;
        }
        if (null !== $this->crop) {
            $data['crop'] = $this->crop->toArray();
        }

        return $data;
    }
}
