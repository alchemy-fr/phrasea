# Admin bundle

This bundle provides an admin based on EasyAdminBundle.

## Installation

> !! Ensure this bundle is declared before `EasyAdminBundle` in `config/bundles.php`

```yaml
# config/packages/admin.yaml
alchemy_admin:
  service:
    title: My Service
    name: my-service

easy_admin:
  entities:
    # ...
```

```yaml
# config/packages/security.yaml

security:
    role_hierarchy:
        ROLE_ADMIN: ROLE_USER
        ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        admin:
            pattern:    ^/admin
            stateless:  false
            anonymous:  ~
            logout:
                path: alchemy_admin_logout
                target: easyadmin
            guard:
                authenticators:
                    - 'alchemy_remote.login_form.admin'

    access_control:
        - { path: ^/admin/login$, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/admin/auth/, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

```yaml
# config/routes/admin.yaml

alchemy_admin_bundle:
  resource: '@AlchemyAdminBundle/Resources/config/routes.yaml'
  prefix: /admin
```

## Override AdminController

```php
<?php
# src/Admin/AdminController.php

declare(strict_types=1);

namespace App\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

class AdminController extends EasyAdminController
{
    // Some custom methods
    // @see https://symfony.com/doc/master/bundles/EasyAdminBundle/book/complex-dynamic-backends.html#admincontroller-properties-and-methods
}
```

```yaml
# config/routes/admin.yaml

# ...

easy_admin_bundle:
  resource: 'App\Admin\AdminController'
  type:     annotation
  prefix:   /admin

```
