<?php

declare(strict_types=1);

namespace App\Tests\Unit\Consumer;

use App\Consumer\TargetPrivateOrigins;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TargetPrivateOriginsTest extends TestCase
{
    #[DataProvider('getCases')]
    public function testIsAllowed(?string $databoxApiUrl, ?string $privateOrigins, string $url, bool $expected): void
    {
        $origins = new TargetPrivateOrigins($databoxApiUrl, $privateOrigins);

        $this->assertSame($expected, $origins->isAllowed($url));
    }

    public static function getCases(): array
    {
        $databox = 'https://api-databox.phrasea.local';

        return [
            'Databox origin, any path' => [$databox, null, 'https://api-databox.phrasea.local/integrations/uploader/incoming-uploads', true],
            'Databox origin, explicit default port' => [$databox, null, 'https://api-databox.phrasea.local:443/foo', true],
            'Databox URL with explicit default port' => ['https://api-databox.phrasea.local:443/', null, 'https://api-databox.phrasea.local/foo', true],
            'Databox origin, host case' => [$databox, null, 'https://API-Databox.Phrasea.LOCAL/foo', true],
            'Databox URL with a non default port' => ['https://api-databox.phrasea.local:8443', null, 'https://api-databox.phrasea.local:8443/foo', true],
            'Other port' => [$databox, null, 'https://api-databox.phrasea.local:8443/foo', false],
            'Other scheme' => [$databox, null, 'http://api-databox.phrasea.local/foo', false],
            'Other host' => [$databox, null, 'https://elasticsearch.phrasea.local/foo', false],
            'Dotless stack host' => [$databox, null, 'http://databox-api/foo', false],
            'Unsupported scheme' => ['ftp://api-databox.phrasea.local', null, 'ftp://api-databox.phrasea.local/foo', false],
            'Invalid URL' => [$databox, null, 'not an url', false],
            'Listed origin' => [$databox, 'https://phraseanet.local', 'https://phraseanet.local/api/v1/upload/enqueue/', true],
            'Listed dotless origin' => [$databox, 'http://phraseanet', 'http://phraseanet/api/v1/upload/enqueue/', true],
            'List with spaces and empty entries' => [$databox, ' , https://phraseanet.local ,,http://other.local:8080 , ', 'http://other.local:8080/enqueue', true],
            'List with an invalid entry' => [$databox, 'not an url, ftp://phraseanet.local, https://phraseanet.local', 'https://phraseanet.local/enqueue', true],
            'Unlisted origin' => [$databox, 'https://phraseanet.local', 'https://phraseanet2.local/enqueue', false],
            'Empty Databox URL, listed origin' => ['', 'https://phraseanet.local', 'https://phraseanet.local/enqueue', true],
            'Empty Databox URL' => ['', 'https://phraseanet.local', 'https://api-databox.phrasea.local/foo', false],
            'Nothing configured' => [null, null, 'https://api-databox.phrasea.local/foo', false],
            'Empty list' => [null, '', 'https://api-databox.phrasea.local/foo', false],
        ];
    }
}
