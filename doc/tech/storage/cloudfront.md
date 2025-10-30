# Setup CloudFront

```dotenv
CLOUD_FRONT_URL=https://xxxxx.cloudfront.net
CLOUD_FRONT_PRIVATE_KEY=MIIEpQIBAAKCAQEAzd8YI+qHSkEXuB+ZIQXIBO+qFxLmhqmgKVSNn3ErDIf4ouR2\n...
CLOUD_FRONT_KEY_PAIR_ID=XXXXXXXXX
CLOUD_FRONT_REGION=eu-west-3
```

## Allow download

It is necessary to whitelist the `response-content-disposition=attachment` query string so that it would be forwarded to S3 and the response would have the appropriate content disposition.

Please follow these steps:

- Visit your CloudFront console on AWS
- Select your distribution
- Select the ‘Behaviors’ tab
- Select the behavior list item and click ‘Edit’
- Click ‘Create a new policy’ near the heading ‘Cache Policy’
- Under ‘Cache key contents’ > ‘Query strings’ > ‘Whitelist’ add response-content-disposition.
- Save your new policy
