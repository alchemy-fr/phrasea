# Admin bundle

This bundle provides an admin based on EasyAdminBundle.

## Installation

> !! Ensure this bundle is declared before `EasyAdminBundle` in `config/bundles.php`

```yaml
# config/packages/alchemy_admin.yaml
alchemy_admin:
  service:
    title: My Service
    name: my-service
```

```yaml
# config/packages/security.yaml

security:
    firewalls:
        admin:
            pattern:    ^/admin
            stateless:  false
            access_denied_handler: alchemy_admin.access_denied_handler
            logout:
                path: alchemy_auth_logout
                target: easyadmin
            custom_authenticators:
                - Alchemy\AuthBundle\Security\OAuthAuthorizationAuthenticator

    access_control:
        - { path: ^/admin/login$, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

```yaml
# config/routes/alchemy_admin.yaml

alchemy_admin_bundle:
  resource: '@AlchemyAdminBundle/Resources/config/routes.yaml'
  prefix: /admin
```
