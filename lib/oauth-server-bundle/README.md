# OAuth server bundle

This bundle provide FOSOAuthServerBundle with a simplified confuration.
It extends it with allowed scopes per OAuth client.

If user is authenticated with a RemoteAuthToken (see [remote-auth-bundle](../remote-auth-bundle/README.md)), then user ID will be provided automatically.

## Configuration

```yaml
# config/packages/alchemy_oauth_server.yml

alchemy_oauth_server:
  access_token_lifetime: 7776000
  scopes:
    - scope1
    - scope2
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
