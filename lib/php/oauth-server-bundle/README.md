# OAuth server bundle

This bundle provide FOSOAuthServerBundle with a simplified confuration.
It extends it with allowed scopes per OAuth client.

If user is authenticated with a RemoteAuthToken (see [auth-bundle](../auth-bundle/README.md)), then user ID will be provided automatically.

## Configuration

```yaml
# config/packages/alchemy_oauth_server.yml

alchemy_oauth_server:
  access_token_lifetime: 7776000
  scopes:
    - scope1
    - scope2
```

Add routes:

```yaml
# config/routes/alchemy_oauth_server.yml
alchemy_oauth_server:
  resource: "@AlchemyOAuthServerBundle/Resources/config/routing/routes.yaml"
```

Configure your firewalls:

```yaml
# app/config/package/security.yaml
security:
    firewalls:
        oauth_token:
            pattern:    ^/oauth/v2/token
            security:   false

# Optionally:
#        oauth_authorize:
#            pattern:    ^/oauth/v2/auth
#            # Add your favorite authentication process here

        api:
            anonymous: ~
            stateless: true
            fos_oauth:  true
```

### Access token delivered to users

By default this bundle generates entities without the user column.
It allows application to deliver access_token to machines (grant_type=client_credentials).

If your application should deliver tokens to users (grant_type=password or authorization_code) then you provider the user class of your application:

```yaml
alchemy_oauth_server:
    user:
      class: App\Entity\User
```
