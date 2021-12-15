# Auth service

Auth service is part of the Alchemy ecosystem.
Its role is to share authentication across services (SSO).

Service wraps the following end projects:
- Auth API (back end)

## Setup

### Env variables

```bash
DEFAULT_USER_EMAIL=admin@alchemy.fr
DEFAULT_USER_PASSWORD=<A_PASSWORD>
AUTH_API_BASE_URL=https://alchemy-auth.com
```

### User management

Go to the auth-api-php container:

```bash
docker-compose exec --user app auth_php /bin/sh
```

Then refer to the Auth API [documentation](./api/README.md)

# Further reading

- [User import](./doc/user-import.md)
- [Configuring SAML IdP](./doc/saml.md)
