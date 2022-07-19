# Test bundle

This trait does not provide bootKernel on purpose because you may need to add custom logic or combine other traits.
So you need to add `bootKernel` method to your (abstract) test class:

```php
namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Alchemy\TestBundle\Helper\FixturesTrait;

abstract class MyBaseTestCase extends KernelTestCase
{
    use FixturesTrait;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }
}

class MyTest extends MyBaseTestCase
{
    public function testItIsWorking(): void
    {
        static::enableFixtures();
        
        $client = self::createClient();
        $client->disableReboot();
        
        $client->request(...);
    }
}
```
