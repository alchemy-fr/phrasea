# Auth service

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
bin/console doctrine:schema:create mobile
```

> Replace `mobile` by your client logical name.

Then copy credential given by the output.

> Note that client ID has been suffixed by a random hash for security.
