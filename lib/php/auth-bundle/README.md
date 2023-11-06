# Remote auth

This bundle provides a Guard authenticator for services using a SSO.

Add routes:
```yaml
# config/routes/alchemy_auth.yaml
alchemy_auth_security:
    prefix: /admin
    resource: '@AlchemyAuthBundle/Resources/routing/security.yaml'

alchemy_auth_permissions:
  prefix: /admin
  resource: '@AlchemyAuthBundle/Resources/routing/permissions.yaml'
```

Example of configuration:
```yaml
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

        api:
            stateless: true
            custom_authenticators:
                - Alchemy\AuthBundle\Security\AccessTokenAuthenticator
```
