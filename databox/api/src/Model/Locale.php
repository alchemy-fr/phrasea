<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\LocaleProvider;
use Symfony\Component\Intl\Locales;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'locale',
    operations: [
        new Get(),
        new GetCollection(),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_READ],
    ],
    provider: LocaleProvider::class,
)]
class Locale
{
    private const string GROUP_READ = 'locale:r';

    public function __construct(
        #[Groups(self::GROUP_READ)]
        #[ApiProperty(identifier: true)]
        public string $id,
        #[Groups(self::GROUP_READ)]
        public string $language,
        #[Groups(self::GROUP_READ)]
        public ?string $region = null,
        #[Groups(self::GROUP_READ)]
        public ?string $variant = null,
        #[Groups(self::GROUP_READ)]
        public ?string $script = null,
    ) {
    }

    #[Groups(self::GROUP_READ)]
    public function getName(): string
    {
        return ucfirst(Locales::getName($this->id));
    }

    #[Groups(self::GROUP_READ)]
    public function getNativeName(): string
    {
        return ucfirst(Locales::getName($this->id, $this->id));
    }
}
