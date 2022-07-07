# OpenID Connect

## Add keycloak to Auth

Add identity provider to `configs/config.json`:

```json
{
  "auth": {
    "identity_providers": [
      {
        "title": "Keycloak",
        "name": "keycloak",
        "type": "oauth",
        "options": {
          "type": "keycloak",
          "client_id": "ps-auth",
          "client_secret": "xxxxxxxxxx",
          "base_url": "https://keycloak.phrasea.local",
          "realm": "master",
          "paths": {
            "identifier": "preferred_username",
            "nickname": "preferred_username",
            "email": "email",
            "firstname": "first_name",
            "lastname": "last_name",
            "groups": "groups" // if claim name from keycloak is "groups"
          }
        }
      }
    ]
  }
}
```
