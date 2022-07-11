# Configuring SAML Identity provider

Auth service will act as a Service Provider (SP) and can be registered to an Identity Provider (IdP).

Here follow the SP variables:

**Entity ID**: `https://{AUTH_API_BASE_URL}/saml/metadata/{idp}`

> Where `{idp}` is the identity provider name declared in config.json

**Assertion URL**: `https://{AUTH_API_BASE_URL}/saml/acs`

**Assertion Binding**: `urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST`

**Logout URL**: `https://{AUTH_API_BASE_URL}/saml/logout`

**Logout Binding**: `urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect`

## Attributes mapping

On each provider we can define attribute mapping.
`username` attribute must result to determine the final username stored in Auth service.

For example, if you want the IdP attribute `email` to be the username, you should set your map as follow:

```json
{
  "title": "IDP test",
  "name": "idp-test",
  "type": "saml",
  "options": {
    "attributes_map": {
      "email": "username"
    }
  }
}
```

## Group mapping

Make the groups from IdP correspond to Auth groups:

```json
{
  "title": "IDP test",
  "name": "idp-test",
  "type": "saml",
  "group_map": {
    "group1": "groupA",
    "group2": "groupB"
  },
  "options": {
    "attributes_map": {
      "email": "username"
    },
    "groups_attribute": "eduPersonAffiliation"
  }
}
```

### Explanations:

Given the following payload received from IdP:

```json
{
  "email": "user@alchemy.fr",
  "user_groups": [
    "group1"
  ]
}
```

With the configuration above, the following user will be produced in Auth:

```json
{
  "username": "user@alchemy.fr",
  "groups": [
    "groupA"
  ]
}
```

### Example users

|UID | Username | Password | Group | Email |
|---|---|---|---|---|
|1 | user1 | user1pass | group1 | user1@example.com |
|2 | user2 | user2pass | group2 | user2@example.com |
