# Remote auth

This bundle provides a Guard authenticator for services using a SSO.

Add routes:
```yaml
# config/routes/auth.yaml
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
    providers:
        remote_users:
            id: Alchemy\AuthBundle\Security\RemoteUserProvider
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
            asset: true
            custom_authenticators:
                - Alchemy\AuthBundle\Security\AccessTokenAuthenticator
