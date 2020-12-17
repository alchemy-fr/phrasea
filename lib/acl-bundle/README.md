# ACL bundle

## Project configuration

Add the entities you want to extend with ACL:

```yaml
# config/packages/alchemy_acl.yaml
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
# config/routes/alchemy_acl.yaml
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

### Definitions

- `userType`
Can be `user` or `group`


- `userId`
The user ID or the group ID (depending on the `userType`).
If the value is NULL, then the ACE allows everybody.


- `objectType`
Depending on the application.
Rely on the object you have defined:
```yaml
alchemy_acl:
  objects:
    publication: App\Entity\Publication
    asset: App\Entity\Asset
```

In this application, `objectType` can be either `publication` or `asset`.


- `objectId`
If the value is NULL, then the ACE is apply to all objects of this `objectType`.
  

### Endpoints

This bundle exposes the following routes to the application:

- `GET /permissions/users` Get all users

-----

- `GET /permissions/groups` Get all groups

-----
- `GET /permissions/aces` Get access control entries (ACEs)
Available query filters:
- `userType` (`user` or `group`)
- `userId`
- `objectType`
- `objectId`

Examples:
```bash
# List all ACEs of an object
curl {HOST}/permissions/aces?objectType=publication&objectId=pub-42

# List all ACEs of a group
curl {HOST}/permissions/aces?userType=group&userId=g-42

# List all ACEs of a user
curl {HOST}/permissions/aces?userType=user&userId=u-42

# List all ACEs of a user on an object
curl {HOST}/permissions/aces?userType=user&userId=u-42&objectType=publication&objectId=pub-42
```

-----

- `PUT /permissions/ace` Add or update access control entry (ACE)

You must provide the following body:
```json
{
    "userType": "user",
    "userId": "the-user-id",
    "objectType": "publication",
    "objectId": "the-publication-id",
    "mask": 7
}
```

-----

- `DELETE /permissions/ace` Remove access control entry (ACE)
```json
{
    "userType": "user",
    "userId": "the-user-id",
    "objectType": "publication",
    "objectId": "the-publication-id"
}
```
