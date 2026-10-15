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

## Further Reading

- [Uploader Configuration](./configuration.md)
- [Request Flow](./request_flow.md)
- [Form Configuration](./form_config.md)
- [Form Steps](./form-steps.md)
