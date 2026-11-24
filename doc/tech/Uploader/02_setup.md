---
title: Setup
---

# Uploader Setup

This guide explains how to configure the Uploader service to connect with Phraseanet and prepare it for development or production use.

## 1. Register the Uploader Application in Phraseanet

- Go to your Phraseanet instance (e.g., `https://<alpha.preprod.alchemyasp.com>/developers/application/new/`).
- Create a new application (suggested name: `uploader`).
- Generate a client token for the application.

> **Note:** The generated token is linked to your user account. The Uploader will access Phraseanet using your user credentials.
> 
> **TODO:** In the future, Phraseanet should provide an API key instead of a user OAuth token for better security and flexibility.

## 2. Configure Environment Variables

Override the relevant environment variables in your `.env` or `.env.local` file:

```bash
DEV_MODE=false
CLIENT_ID=<THE_CLIENT_ID>
CLIENT_SECRET=<A_SECRET>
```

## 3. Development Tips

To avoid sending commit requests to the consumer target during development, you can set:

```bash
ASSET_CONSUMER_ACCESS_TOKEN=avoid
```

This prevents actual asset consumption and is useful for testing.

## 4. Targets on the Private Network

The URL of a target is set by an Uploader admin: on each commit, the worker notifies it with a `POST`.
It reaches the private network only for the origin (scheme, host and port) of `DATABOX_API_URL` and for the origins listed in `UPLOADER_TARGET_PRIVATE_ORIGINS`, comma separated and empty by default.
A target on any other host resolving to a private address (another service of the stack, an internal Phraseanet…) is refused unless its origin is listed:

```bash
UPLOADER_TARGET_PRIVATE_ORIGINS=https://phraseanet.local,http://phraseanet-internal:8080
```

## Further Reading

- [Uploader Configuration](./configuration.md)
- [Request Flow](./request_flow.md)
- [Form Configuration](./form_config.md)
