<?php

declare(strict_types=1);

namespace App\Fixture\Faker;

use Faker\Provider\Base as BaseProvider;

/**
 * Business labels of the dev fixtures workspaces (news agency, fashion brand, natural history
 * museum, recording studio). The functions taking a $subject (the asset of the attribute fixture) return values
 * that stay consistent for that subject: a headline and its caption describe the same scene,
 * the scientific and common names designate the same species, the wholesale price follows the
 * retail one.
 */
class ThematicFaker extends BaseProvider
{
    /**
     * Headline, English caption and French caption; "%s" is the city where the asset was taken.
     *
     * @var list<array{string, string, string}>
     */
    private const array NEWS_SCENES = [
        ['Crowds gather in %s on election night', 'Supporters wait for the first results outside a campaign headquarters in %s.', 'Des partisans attendent les premiers résultats devant un QG de campagne, à %s.'],
        ['Floodwaters recede across %s', 'Residents return to their homes as the river falls back below its banks in %s.', 'Les habitants regagnent leurs maisons alors que le fleuve retrouve son lit, à %s.'],
        ['Marathon runners brave the heat in %s', 'Runners cool off at a water station on the course of the %s marathon.', 'Des coureurs se rafraîchissent à un ravitaillement du marathon de %s.'],
        ['Climate activists march through %s', 'Thousands of young protesters walk through the centre of %s demanding faster climate action.', 'Des milliers de jeunes manifestants défilent dans le centre de %s pour réclamer une action climatique plus rapide.'],
        ['Night market reopens in %s', 'Stallholders hang their lanterns as the night market returns to %s after months of works.', 'Les marchands accrochent leurs lanternes pour le retour du marché de nuit de %s après des mois de travaux.'],
        ['Firefighters contain blaze on the outskirts of %s', 'Firefighters work through the night to stop a forest fire spreading towards %s.', 'Les pompiers luttent toute la nuit pour empêcher un feu de forêt de gagner %s.'],
        ['Transit strike brings %s to a standstill', 'Commuters queue for buses on the first day of the public transport strike in %s.', 'Des usagers font la queue pour un bus au premier jour de la grève des transports à %s.'],
        ['Street artists repaint the old harbour in %s', 'Painters finish a giant mural on the warehouses of the old harbour in %s.', 'Des peintres achèvent une fresque géante sur les entrepôts du vieux port de %s.'],
        ['Farmers rally outside parliament in %s', 'Tractors line the avenue as farmers protest against new import rules in %s.', 'Des tracteurs bordent l\'avenue lors de la manifestation des agriculteurs contre les nouvelles règles d\'importation, à %s.'],
        ['Heatwave empties the streets of %s', 'A lone cyclist crosses a deserted square as temperatures pass 40°C in %s.', 'Un cycliste traverse une place déserte alors que le thermomètre dépasse 40 °C à %s.'],
        ['New tram line opens in %s', 'Passengers board the first tram on the new line linking the suburbs to central %s.', 'Des passagers montent dans la première rame de la ligne reliant la banlieue au centre de %s.'],
        ['Fans celebrate title win in %s', 'Supporters light flares in the main square of %s after the final whistle.', 'Des supporters allument des fumigènes sur la grand-place de %s après le coup de sifflet final.'],
        ['Snowstorm grounds flights in %s', 'Ground crews clear the runway as all departures are suspended in %s.', 'Des agents dégagent la piste alors que tous les départs sont suspendus à %s.'],
        ['Jazz festival opens in %s', 'A brass band leads the opening parade of the jazz festival through the streets of %s.', 'Une fanfare mène la parade d\'ouverture du festival de jazz dans les rues de %s.'],
        ['Housing protest blocks the centre of %s', 'Tenants gather in front of the town hall of %s to denounce rising rents.', 'Des locataires se rassemblent devant l\'hôtel de ville de %s pour dénoncer la hausse des loyers.'],
        ['Ancient mosaic unearthed in %s', 'Archaeologists clean a Roman mosaic discovered during roadworks in %s.', 'Des archéologues nettoient une mosaïque romaine découverte lors de travaux de voirie à %s.'],
        ['Cyclists take over the ring road in %s', 'Thousands of riders join the car-free day on the ring road of %s.', 'Des milliers de cyclistes participent à la journée sans voiture sur le périphérique de %s.'],
        ['Candlelight vigil held in %s', 'Mourners leave candles and flowers at a makeshift memorial in %s.', 'Des anonymes déposent bougies et fleurs devant un mémorial improvisé à %s.'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const array NEWS_TITLES = [
        'release' => [
            'Northbeam Press appoints a new Europe editor',
            'Northbeam Press named Agency of the Year',
            'Annual press freedom report',
            'Northbeam launches a French-language wire service',
            'Northbeam Press partners with regional newspapers',
            'Statement on the coverage of the general election',
            'Northbeam Press opens a bureau in Nairobi',
            'Photo competition 2026: call for entries',
            'Northbeam Press annual results 2025',
            'New editorial guidelines on generated imagery',
        ],
        'essay' => [
            'Life on the river: a year with the barge families',
            'The last night shift at the steelworks',
            'Coastline: communities facing rising seas',
            'Behind the scenes of the national ballet',
            'Harvest season in the high valleys',
            'Inside the busiest emergency room in town',
            'Portraits of the marathon volunteers',
            'Winter on the islands',
            'The new faces of street food',
            'One day at the border crossing',
        ],
    ];

    private const array NEWS_PHOTOGRAPHERS = [
        'Maya Lindqvist',
        'Tomás Ferreira',
        'Aiko Tanaka',
        'Samuel Okafor',
        'Claire Dubois',
        'Jonah Whitaker',
        'Leila Haddad',
        'Piotr Nowak',
    ];

    private const array NEWS_KEYWORDS = [
        'politics', 'election', 'protest', 'climate', 'flood', 'wildfire', 'transport', 'strike',
        'football', 'tennis', 'rugby', 'festival', 'music', 'cinema', 'heritage', 'archaeology',
        'agriculture', 'housing', 'weather', 'portrait', 'crowd', 'aerial', 'night',
    ];

    private const array NEWS_EDITOR_NOTES = [
        'Check the spelling of the names with the desk before release.',
        'Model release pending: editorial use only.',
        'Second edit requested by the picture desk.',
        'Do not crop: the credit is part of the frame.',
        'Caption to be updated with the official figures.',
        'Embargo agreed with the ministry press office.',
    ];

    /**
     * WebVTT cues by locale.
     *
     * @var array<string, list<string>>
     */
    private const array TRANSCRIPT_CUES = [
        'en' => [
            'Welcome to Hollowtone Recording.',
            'Rolling now, this is take one.',
            'Let\'s bring the vocals up a little in the chorus.',
            'That\'s a wrap, thanks everyone.',
        ],
        'fr' => [
            'Bienvenue chez Hollowtone Recording.',
            'On enregistre, c\'est la première prise.',
            'On remonte un peu la voix sur le refrain.',
            'C\'est dans la boîte, merci à tous.',
        ],
    ];

    /**
     * Styles of each product category of the fashion brand.
     *
     * @var array<string, list<string>>
     */
    private const array PRODUCT_STYLES = [
        'dress' => ['Wrap Dress', 'Slip Dress', 'Shirt Dress', 'Tiered Dress', 'Pleated Midi Dress', 'Tea Dress'],
        'knit' => ['Cable Knit Jumper', 'Ribbed Cardigan', 'Fisherman Jumper', 'Mohair Vest', 'Fair Isle Jumper', 'Cropped Cardigan'],
        'shirt' => ['Oxford Shirt', 'Poplin Shirt', 'Camp-Collar Shirt', 'Grandad Shirt', 'Chambray Shirt', 'Linen Overshirt'],
        'outer' => ['Trench Coat', 'Chore Jacket', 'Field Jacket', 'Quilted Jacket', 'Peacoat', 'Harrington Jacket'],
        'bag' => ['Tote Bag', 'Crossbody Bag', 'Bucket Bag', 'Weekender', 'Saddle Bag', 'Clutch'],
        'shoe' => ['Loafer', 'Espadrille', 'Derby', 'Chelsea Boot', 'Strappy Sandal', 'Canvas Trainer'],
    ];

    private const array PRODUCT_LINES = ['Coastline', 'Marina', 'Dune', 'Harbour', 'Saltmarsh', 'Tidal', 'Night Garden', 'Orchard'];

    /**
     * Retail price range (in euros) of each product category.
     *
     * @var array<string, array{int, int}>
     */
    private const array PRODUCT_PRICES = [
        'dress' => [120, 390],
        'knit' => [95, 340],
        'shirt' => [75, 180],
        'outer' => [190, 690],
        'bag' => [140, 520],
        'shoe' => [110, 360],
    ];

    /**
     * "%s" is the product name.
     *
     * @var array<string, string>
     */
    private const array MARKETING_COPY = [
        'en' => '<p><strong>%s</strong>, designed in our London studio and made to last well beyond one season.</p><p>Responsibly sourced materials, finished by hand.</p>',
        'fr' => '<p><strong>%s</strong>, dessiné dans notre studio londonien et conçu pour durer bien plus d\'une saison.</p><p>Matières sourcées de manière responsable, finitions à la main.</p>',
        'it' => '<p><strong>%s</strong>, disegnato nel nostro studio di Londra e pensato per durare ben oltre una stagione.</p><p>Materiali di provenienza responsabile, rifiniture a mano.</p>',
    ];

    private const array FASHION_PHOTOGRAPHERS = [
        'Ines Moreau',
        'Kofi Mensah',
        'Sofia Bianchi',
        'Harriet Cole',
    ];

    /**
     * Scientific name, English common name and French common name, by group of specimens.
     *
     * @var array<string, list<array{string, string, string}>>
     */
    private const array SPECIMENS = [
        'orchid' => [
            ['Ophrys apifera', 'Bee orchid', 'Ophrys abeille'],
            ['Orchis mascula', 'Early-purple orchid', 'Orchis mâle'],
            ['Cypripedium calceolus', 'Lady\'s-slipper orchid', 'Sabot de Vénus'],
            ['Dactylorhiza maculata', 'Heath spotted-orchid', 'Orchis tacheté'],
            ['Anacamptis pyramidalis', 'Pyramidal orchid', 'Orchis pyramidal'],
        ],
        'sundew' => [
            ['Drosera rotundifolia', 'Round-leaved sundew', 'Rossolis à feuilles rondes'],
            ['Drosera anglica', 'Great sundew', 'Rossolis à longues feuilles'],
            ['Drosera intermedia', 'Oblong-leaved sundew', 'Rossolis intermédiaire'],
            ['Dionaea muscipula', 'Venus flytrap', 'Dionée attrape-mouche'],
        ],
        'fern' => [
            ['Asplenium scolopendrium', 'Hart\'s-tongue fern', 'Scolopendre officinale'],
            ['Asplenium trichomanes', 'Maidenhair spleenwort', 'Capillaire des murailles'],
            ['Asplenium ruta-muraria', 'Wall-rue', 'Rue des murailles'],
            ['Asplenium adiantum-nigrum', 'Black spleenwort', 'Doradille noire'],
            ['Asplenium marinum', 'Sea spleenwort', 'Doradille marine'],
        ],
        'rarefern' => [
            ['Vandenboschia speciosa', 'Killarney fern', 'Trichomanès remarquable'],
            ['Hymenophyllum tunbrigense', 'Tunbridge filmy-fern', 'Hyménophylle de Tunbridge'],
            ['Hymenophyllum wilsonii', 'Wilson\'s filmy-fern', 'Hyménophylle de Wilson'],
        ],
        'butterfly' => [
            ['Vanessa atalanta', 'Red admiral', 'Vulcain'],
            ['Aglais io', 'Peacock', 'Paon-du-jour'],
            ['Danaus plexippus', 'Monarch', 'Monarque'],
            ['Apatura iris', 'Purple emperor', 'Grand Mars changeant'],
            ['Vanessa cardui', 'Painted lady', 'Belle-Dame'],
            ['Limenitis camilla', 'White admiral', 'Petit Sylvain'],
        ],
        'beetle' => [
            ['Lucanus cervus', 'Stag beetle', 'Lucane cerf-volant'],
            ['Dorcus parallelipipedus', 'Lesser stag beetle', 'Petite biche'],
            ['Sinodendron cylindricum', 'Rhinoceros beetle', 'Sinodendron cylindrique'],
            ['Platycerus caraboides', 'Blue stag beetle', 'Platycère caraboïde'],
        ],
        'owl' => [
            ['Bubo bubo', 'Eurasian eagle-owl', 'Grand-duc d\'Europe'],
            ['Strix aluco', 'Tawny owl', 'Chouette hulotte'],
            ['Athene noctua', 'Little owl', 'Chevêche d\'Athéna'],
            ['Asio otus', 'Long-eared owl', 'Hibou moyen-duc'],
            ['Otus scops', 'Eurasian scops owl', 'Petit-duc scops'],
        ],
        'mineral' => [
            ['Quartz', 'Rock crystal', 'Cristal de roche'],
            ['Quartz var. amethyst', 'Amethyst', 'Améthyste'],
            ['Pyrite', 'Fool\'s gold', 'Pyrite'],
            ['Fluorite', 'Fluorspar', 'Fluorine'],
            ['Malachite', 'Malachite', 'Malachite'],
            ['Galena', 'Galena', 'Galène'],
        ],
    ];

    private const array COLLECTORS = [
        'Edith Marlowe',
        'Arthur Pennington',
        'Ines Vasquez',
        'Hollis Grant',
        'Margaret Ashdown',
        'Theo Brannigan',
    ];

    private const array CONDITION_REPORTS = [
        'Stable. Minor foxing on the label.',
        'Pins corroded: to be replaced at the next handling.',
        'Colour fading on the exposed side.',
        'Fragile: handle with gloves only.',
        'Good condition, no active pest damage.',
        'Mount detached at one corner, re-glued in 2025.',
    ];

    /**
     * Titles of the recording studio, by kind. The albums, the tracks (4 per album: rock, jazz,
     * electronic, folk), the sessions, the clips and the voice-overs are listed in the order of
     * their fixtures, so that a title matches the content of its themed media file.
     *
     * @var array<string, list<string>>
     */
    private const array STUDIO_TITLES = [
        'album' => [
            'Static Bloom',
            'Blue Hour',
            'Signal Garden',
            'Kindling',
        ],
        'track' => [
            'Paper Satellites',
            'Fault Lines',
            'Neon Undertow',
            'Last Train Out',
            'Slow Burn on Mercer Street',
            'Midnight Tram',
            'Velvet Static',
            'Afterhours Waltz',
            'Pulse Theory',
            'Glass Circuit',
            'Lowlight',
            'Northern Relay',
            'Hollow Oak',
            'Riverstone',
            'Lanterns in the Rye',
            'Far Field',
        ],
        'session' => [
            'Tracking day – vocals and bass',
            'Tracking day – guitars and strings',
            'Tracking day – drums and brass',
            'Tracking day – piano and synth',
        ],
        'clip' => [
            'Live under the stage lights',
            'Official music video',
            'Rooftop session at night',
            'Live in the hall',
            'Club gig',
            'Packed house',
        ],
        'voiceover' => [
            'Kestrel FM – station ident',
            'Morning Roast – radio spot',
            'Kestrel FM – weekend travel update',
            'Vélo Cité – radio spot',
            'Kestrel After Dark – show promo',
            'Banque du Port – radio spot',
        ],
    ];

    /**
     * Artists recorded by the studio, one per album.
     */
    private const array STUDIO_ARTISTS = [
        'The Paper Satellites',
        'Mira Okonkwo Quartet',
        'Circuit Fern',
        'Ada Rowan & the Lantern Band',
    ];

    private const array STUDIO_ENGINEERS = [
        'Nina Halvorsen',
        'Marcus Bell',
        'Yusuf Demir',
        'Lena Fischer',
        'Raphaël Martin',
    ];

    private const array MUSIC_KEYS = [
        'C major', 'G major', 'D major', 'A major', 'E major', 'F major', 'B♭ major', 'E♭ major',
        'A minor', 'E minor', 'B minor', 'D minor', 'G minor', 'C minor', 'F♯ minor',
    ];

    /**
     * @var array<string, array<string, mixed>> values already picked, by subject then by key
     */
    private array $picked = [];

    public function newsHeadline(mixed $subject, string $city): string
    {
        return sprintf($this->newsScene($subject)[0], $city);
    }

    public function newsCaption(mixed $subject, string $city, string $locale): string
    {
        return sprintf($this->newsScene($subject)['fr' === $locale ? 2 : 1], $city);
    }

    /**
     * @param string $kind "release" or "essay"
     */
    public function newsTitle(string $kind): string
    {
        return static::randomElement(self::NEWS_TITLES[$kind]);
    }

    public function newsPhotographer(): string
    {
        return static::randomElement(self::NEWS_PHOTOGRAPHERS);
    }

    public function newsKeyword(): string
    {
        return static::randomElement(self::NEWS_KEYWORDS);
    }

    public function newsEditorNote(): string
    {
        return static::randomElement(self::NEWS_EDITOR_NOTES);
    }

    public function webVttTranscript(string $locale): string
    {
        $vtt = "WEBVTT\n";
        foreach (self::TRANSCRIPT_CUES[$locale] as $i => $cue) {
            $vtt .= sprintf("\n00:00:%02d.000 --> 00:00:%02d.000\n%s\n", $i * 5, ($i + 1) * 5, $cue);
        }

        return $vtt;
    }

    public function productName(mixed $subject, string $category): string
    {
        return $this->product($subject, $category)['name'];
    }

    public function productSku(): string
    {
        return static::toUpper(static::bothify('LR-####-??'));
    }

    public function retailPrice(mixed $subject): int
    {
        return $this->product($subject)['price'];
    }

    public function wholesalePrice(mixed $subject): int
    {
        return (int) round($this->product($subject)['price'] / 2.4);
    }

    public function marketingCopy(mixed $subject, string $locale): string
    {
        return sprintf(self::MARKETING_COPY[$locale], htmlspecialchars($this->product($subject)['name']));
    }

    public function fashionPhotographer(): string
    {
        return static::randomElement(self::FASHION_PHOTOGRAPHERS);
    }

    public function specimenScientificName(mixed $subject, string $group): string
    {
        return $this->specimen($subject, $group)[0];
    }

    public function specimenCommonName(mixed $subject, string $locale): string
    {
        return $this->specimen($subject)['fr' === $locale ? 2 : 1];
    }

    /**
     * Museum inventory number, e.g. "FH.1932.0457".
     */
    public function inventoryNumber(): string
    {
        return sprintf('FH.%d.%04d', static::numberBetween(1872, 2026), static::numberBetween(1, 9999));
    }

    public function specimenCollector(): string
    {
        return static::randomElement(self::COLLECTORS);
    }

    public function conditionReport(): string
    {
        return static::randomElement(self::CONDITION_REPORTS);
    }

    /**
     * @param string   $kind "album", "track", "session", "clip" or "voiceover"
     * @param int|null $n    number of the fixture (from 1): the same number gives the same title;
     *                       a random title without it
     */
    public function studioTitle(string $kind, ?int $n = null): string
    {
        return $this->nthOrRandom(self::STUDIO_TITLES[$kind], $n);
    }

    /**
     * @param int|null $n number of the album (from 1): its artist; a random artist without it
     */
    public function studioArtist(?int $n = null): string
    {
        return $this->nthOrRandom(self::STUDIO_ARTISTS, $n);
    }

    public function studioEngineer(): string
    {
        return static::randomElement(self::STUDIO_ENGINEERS);
    }

    /**
     * Musical key, e.g. "A minor" or "E♭ major".
     */
    public function musicKey(): string
    {
        return static::randomElement(self::MUSIC_KEYS);
    }

    /**
     * @param list<string> $values
     */
    private function nthOrRandom(array $values, ?int $n): string
    {
        if (null === $n) {
            return static::randomElement($values);
        }

        return $values[($n - 1) % count($values)];
    }

    /**
     * @return array{string, string, string}
     */
    private function newsScene(mixed $subject): array
    {
        return $this->pick($subject, 'scene', fn (): array => static::randomElement(self::NEWS_SCENES));
    }

    /**
     * @param string|null $category required on the first call for the subject
     *
     * @return array{name: string, price: int}
     */
    private function product(mixed $subject, ?string $category = null): array
    {
        return $this->pick($subject, 'product', function () use ($category): array {
            if (null === $category) {
                throw new \LogicException('productName() must be called before the other product functions of the same asset');
            }
            [$min, $max] = self::PRODUCT_PRICES[$category];

            return [
                'name' => sprintf('%s %s', static::randomElement(self::PRODUCT_LINES), static::randomElement(self::PRODUCT_STYLES[$category])),
                // Ends with 5 or 9, as on a price tag
                'price' => intdiv(static::numberBetween($min, $max), 10) * 10 + static::randomElement([5, 9]),
            ];
        });
    }

    /**
     * @param string|null $group required on the first call for the subject
     *
     * @return array{string, string, string}
     */
    private function specimen(mixed $subject, ?string $group = null): array
    {
        return $this->pick($subject, 'specimen', function () use ($group): array {
            if (null === $group) {
                throw new \LogicException('specimenScientificName() must be called before specimenCommonName() for the same asset');
            }

            return static::randomElement(self::SPECIMENS[$group]);
        });
    }

    private function pick(mixed $subject, string $key, \Closure $generate): mixed
    {
        $subjectKey = is_object($subject) ? 'o'.spl_object_id($subject) : 's'.$subject;

        return $this->picked[$subjectKey][$key] ??= $generate();
    }
}
