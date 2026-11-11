# CloudFront

## Setup

```dotenv
CLOUD_FRONT_URL=https://xxxxx.cloudfront.net
CLOUD_FRONT_PRIVATE_KEY=MIIEpQIBAAKCAQEAzd8YI+qHSkEXuB+ZIQXIBO+qFxLmhqmgKVSNn3ErDIf4ouR2\n...
CLOUD_FRONT_KEY_PAIR_ID=XXXXXXXXX
CLOUD_FRONT_REGION=eu-west-3
```

## Forced response headers

Every signed CloudFront URL carries the `response-content-type` query parameter, and `response-content-disposition` when the file is an attachment (see [Allowed file types and file serving](./03_file_types.md#response-headers-of-signed-urls-read)). They are part of the signed URL, but CloudFront only applies them if it forwards them to S3:

- S3 applies `response-*` overrides only to **signed** requests: the origin must be accessed through an Origin Access Control (or a legacy Origin Access Identity), never as a public bucket.
- The behavior must **forward** both query strings to the origin **and include them in the cache key**, otherwise a response cached for one set of headers is served for another one (or the stored `Content-Type` comes back).

Please follow these steps:

- Visit your CloudFront console on AWS
- Select your distribution
- Select the ‘Behaviors’ tab
- Select the behavior list item and click ‘Edit’
- Click ‘Create a new policy’ near the heading ‘Cache Policy’
- Under ‘Cache key contents’ > ‘Query strings’ > ‘Include specified query strings’, add `response-content-type` and `response-content-disposition` (query strings in the cache key are forwarded to the origin).
- Save your new policy

Without this policy, files are served with the `Content-Type` declared by the uploading client and inline: a HTML or SVG file would run on the CDN domain.

## Response headers policy

Also attach a **response headers policy** to the behavior (‘Response headers policy’ > ‘Create policy’ > ‘Custom headers’, with ‘Origin override’ checked):

| Header                    | Value                                  |
|---------------------------|----------------------------------------|
| `X-Content-Type-Options`  | `nosniff`                              |
| `Content-Security-Policy` | `sandbox; default-src 'none'`          |

`nosniff` keeps browsers from guessing a type other than the forced one; the sandboxing CSP prevents a document opened from the CDN from running scripts or loading anything. Check that the PDF viewers of the applications still work after enabling the CSP.

Prefer a CDN domain that does not share its registrable domain with the applications (see [Recommendations](./03_file_types.md#recommendations)).
