# ACL bundle

## Project configuration

Add the entities you want to extend with ACL:

```yaml
# config/packages/alchemy_acl.yml
alchemy_acl:
  objects:
    publication: App\Entity\Publication
    asset: App\Entity\Asset
```

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
alchemy_acl_api:
  resource: "@AlchemyAclBundle/Resources/routing/permissions_api.yaml"
  prefix: /permissions
alchemy_acl_admin:
  resource: "@AlchemyAclBundle/Resources/routing/permissions_admin.yaml"
  prefix: /admin/permissions
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

## API

This bundle exposes the following routes to the application:

- `GET /permissions/users` Get all users
- `GET /permissions/groups` Get all groups
- `PUT /permissions/ace` Add or update access control entry (ACE)
- `DELETE /permissions/ace` Remove access control entry (ACE)

For the `/permissions/ace` endpoints you must provide the following data:
```json
{
    "userType": "user",
    "userId": "the-user-id",
    "objectType": "publication",
    "objectId": "the-publication-id",
    "mask": 7 // For PUT only
}
```
