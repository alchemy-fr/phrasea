# Auth API

## Installation

Install dependencies:

```bash
composer install
```

Create database and schema:

```bash
bin/console doctrine:database:create \
    && bin/console doctrine:schema:create
```

Create the OAuth client:

```bash
bin/console app:create-client mobile
```

> Replace `mobile` by your client logical name.

Then copy credential given by the output.

> Note that client ID has been suffixed by a random hash for security.

### User management with bin/console

Create user:
```bash
bin/console app:user:create user@alchemy.fr -p s3cr3t --roles ROLE_SUPER_ADMIN
```

Edit user's password:
```bash
bin/console app:user:create user@alchemy.fr -p s3cr3t_2 --update-if-exist
```

Grant user roles:
```bash
bin/console app:user:set-roles user@alchemy.fr "ROLE_SUPER_ADMIN,ROLE_EDITOR"
```

Revoke user roles:
```bash
bin/console app:user:set-roles user@alchemy.fr ""
```

Remove user:
```bash
bin/console app:user:remove user@alchemy.fr
```

Enable user:
```bash
bin/console app:user:enable user@alchemy.fr
```

Disable user:
```bash
bin/console app:user:enable --disable user@alchemy.fr
```

## API

### Get user

Client consuming `/users/{ID}` must have the `READ_USERS` authorization et should obtain its access token
with the `client_credentials` grant type:

```json
{
	"client_id": "uploader-app_12345",
	"client_secret": "cli3nt_s3cr3t",
	"grant_type": "client_credentials"
}
```
