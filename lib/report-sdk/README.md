# Report SDK

For internal analytics.

This library provide a simple service to push log to the internal analytics server named [report](../../report/README.md).

## Usage

```php
<?php
use GuzzleHttp\Client;
use Alchemy\ReportSDK\ReportClient;

$client = new Client([
    'base_uri' => 'http://report-host',
    'options' => [
        'timeout' => 10,
        'http_errors' => false,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ],
]);
$reportClient = new ReportClient('my-app-name', $client);

$reportClient->pushLog(
    'asset_view', // action 
    '3aaa69a1-acc3-4a2d-9513-acc3fbebacc3', // user_id
    '6b4c3137-aacb-4c9d-81eb-96b5bbbd99b5', // item_id (the asset ID in this example)
    [
        'some' => [
            'extra' => 'data',
        ],
    ], // payload
);
```

### Symfony bundle

See [Symfony bundle](../report-bundle/README.md).
