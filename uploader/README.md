# Upload service

Upload service is part of the Alchemy ecosystem.
Its role is to handle uploaded assets by authenticated users and trigger other services so they can fetch the file.

Service wraps the following end projects:
- Upload API (back end)
- Uploader client (front end)

## Setup

### Create "uploader" application on Phraseanet

- Create new application (i.e `uploader`) at https://<alpha.preprod.alchemyasp.com>/developers/application/new/
- Generate a token

> Note that the token will be link to your user account. Uploader will reach Phraseanet through your user.

> **TODO:** Phraseanet should provide an API key instead of a user OAuth token.

Override environment variables defined in `.env` file:

```bash
DEV_MODE=false
CLIENT_ID=<THE_CLIENT_ID> # NOT the client ID from Phraseanet
CLIENT_RANDOM_ID=<A_RANDOM_HASH>
CLIENT_SECRET=<A_SECRET> # NOT the client secret from Phraseanet
ASSET_CONSUMER_COMMIT_URI=https://alpha.preprod.alchemyasp.com/api/v1/upload/enqueue/
ASSET_CONSUMER_ACCESS_TOKEN=<THE_TOKEN_GOT_FROM_PHRASEANET_APPLICATION>
```

## Development

In order to avoid commit request to consumer target, you can define `ASSET_CONSUMER_ACCESS_TOKEN=avoid`.

# Further reading

- [Upload configuration](./doc/configuration.md)
- [Request flow](./doc/request_flow.md)
- [Form configuration](./doc/form_config.md)
- [Form steps](./doc/form-steps.md)
