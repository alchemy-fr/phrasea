<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Model\QuarantineBatchActionEnum;
use Symfony\Component\Validator\Constraints as Assert;

class QuarantineBatchInput
{
    #[Assert\NotNull]
    public ?QuarantineBatchActionEnum $action = null;

    /**
     * The quarantined assets to resolve. `targetId` (merge as a new version
     * only) is the duplicate receiving the incoming file: it can be omitted
     * when the asset has a single valid target.
     *
     * @var list<array{id: string, targetId?: string|null}>|null
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1, max: QuarantineBatchPrepareInput::MAX_ITEMS)]
    #[Assert\All([
        new Assert\Collection(fields: [
            'id' => [new Assert\NotBlank(), new Assert\Type('string')],
            'targetId' => new Assert\Optional([new Assert\Type('string')]),
        ]),
    ])]
    public ?array $items = null;

    /**
     * Merge only: keep the incoming file as a new version of the existing
     * asset, or discard it. The values of the existing asset are kept either way.
     */
    public bool $addAsVersion = true;
}
