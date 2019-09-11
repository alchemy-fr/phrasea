# Expose service

Expose service is part of the Alchemy ecosystem.
Its role is to expose assets to the Internet.

Service wraps the following end projects:
- Expose API (back end)

## Setup

Override environment variables defined in `.env` file:

```bash
DEV_MODE=false
EXPOSE_CLIENT_ID=<THE_CLIENT_ID> # NOT the client ID from Phraseanet
EXPOSE_CLIENT_RANDOM_ID=<A_RANDOM_HASH>
EXPOSE_CLIENT_SECRET=<A_SECRET> # NOT the client secret from Phraseanet
ASSET_CONSUMER_COMMIT_URI=https://alpha.preprod.alchemyasp.com/api/v1/upload/enqueue/
ASSET_CONSUMER_ACCESS_TOKEN=<THE_TOKEN_GOT_FROM_PHRASEANET_APPLICATION>
```

## Development

In order to avoid commit request to consumer target, you can define `ASSET_CONSUMER_ACCESS_TOKEN=avoid`.

# Further reading

- [Request flow](./doc/request_flow.md)
- [Form configuration](./doc/form_config.md)
- [Form steps](./doc/form-steps.md)
