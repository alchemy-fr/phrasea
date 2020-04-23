# ACL bundle

## Project configuration

### Admin setup

```php
<?php
// 
declare(strict_types=1);

namespace App\Admin;

use Alchemy\AclBundle\Admin\PermissionTrait;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

class AdminController extends EasyAdminController
{
    use PermissionTrait;
}
```

```yaml
# config/routes/admin.yaml
easy_admin_bundle:
    resource: 'App\Admin\AdminController'
    prefix: /admin
    type: annotation

```

```yaml
# config/routes/alchemy_acl.yml
alchemy_acl:
  resource: "@AlchemyOAuthServerBundle/Resources/routing/permissions.yaml"
```

Add redis cache for access token:
```yaml
# config/packages/cache.yaml
framework:
    cache:
        default_redis_provider: redis://redis
        pools:
            accessToken.cache: # You must use this name for auto wiring
                adapter: cache.adapter.redis
```
