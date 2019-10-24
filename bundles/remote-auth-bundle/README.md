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
                path: alchemy_admin_logout
                target: easyadmin
            guard:
                authenticators:
                    - 'alchemy_remote.login_form.admin'

        api:
            anonymous: ~
            stateless: true
            asset: true
            remote_auth: true
