# Remote auth

This bundle provides a Guard authenticator for micro services using a SSO.

Example of configuration:
```yaml
security:
    role_hierarchy:
        ROLE_ADMIN: ROLE_USER
        ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]
    providers:
        remote_users:
            id: Alchemy\RemoteAuthBundle\Security\RemoteUserProvider
    firewalls:
        admin:
            pattern:    ^/admin
            stateless:  false
            anonymous:  ~
            logout:
                path: admin_logout
                target: easyadmin
            guard:
                authenticators:
                    - Alchemy\RemoteAuthBundle\Security\LoginFormAuthenticator

        api:
            anonymous: ~
            stateless: true
            asset: true
            guard:
                authenticators:
                    - Alchemy\RemoteAuthBundle\Security\RemoteAuthAuthenticator
```
