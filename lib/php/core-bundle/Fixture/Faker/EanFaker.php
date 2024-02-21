<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Fixture\Faker;

class EanFaker extends AbstractCachedFaker
{
    public function ean(
    ): string {
        $ean = '';

        foreach (range(1, 13) as $i) {
            $ean .= rand(0, 9);
        }

        return $ean;
    }
}
