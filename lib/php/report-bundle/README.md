# Report bundle

This bundle provide [report-sdk](../report-sdk/README.md) as an extended service using Symfony security.

If user is authenticated, user ID will be provided automatically.

## Configuration

Config is taken from alchemy_core extension:

```yaml
# config/packages/alchemy_core.yml

alchemy_core:
  app_name: my-app-name
```

## Usage

### In a HTTP request context

```php
<?php
namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use Symfony\Component\HttpFoundation\Request;

final class MyController
{
    public function handle(string $id, ReportUserService $reportClient, Request $request)
    {
        // ...
        $reportClient->pushHttpRequestLog(
            $request,
            'asset_download', // action
            $id, // item ID
            [
                'some' => [
                    'extra' => 'data',
                ],
            ], // payload
        );
        // ...
    }
}
```

## In a worker context

```php
<?php
namespace App;

use Alchemy\ReportBundle\ReportUserService;

final class MyService
{
    public function __construct(private readonly ReportUserService $reportClient)
    {
        $this->reportClient = $reportClient;
    }

    public function handle(string $id)
    {
        // ...
        $this->reportClient->pushLog(
            'asset_download', // action
            $id, // item ID
            [
                'some' => [
                    'extra' => 'data',
                ],
            ], // payload
        );
        // ...
    }
}
```
