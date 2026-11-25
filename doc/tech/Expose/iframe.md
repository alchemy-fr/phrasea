---
title: Embedding (iframe)
---

# Embedding (iframe)

The pages of the Expose client can be shown in an iframe of another site.

## What can be embedded

- **A publication**: any page of the client, `/{publication}` or `/{publication}/{asset}`,
  with its top bar and its layout.
- **One asset**: `/embed/{assetId}`, its player alone, filling the frame (image with zoom, video
  with its subtitles, audio, PDF). `assetId` is the id of the asset **in Expose** (`id` of
  `GET /publications/{id}/assets`), not the id of the Databox asset. The page applies the same
  rules as the publication of the asset: security method, stylesheet, terms of use. It warns the
  editors when the publication is disabled.

```html
<iframe
    src="https://expose.example.com/embed/2f0c…"
    width="800"
    height="450"
    allow="fullscreen"
></iframe>
```

Nothing generates these URLs or the HTML code: the integrator builds them (the id of an asset is
in the API responses, or in the back office).

## Allowed sites

The client always sends a `frame-ancestors` directive: by default (`'self'`), its pages can only be
framed by the client itself. The sites allowed to embed them are listed in
`EXPOSE_ALLOWED_FRAME_ANCESTORS` (`ALLOWED_FRAME_ANCESTORS` of the client container), space
separated, as CSP sources:

```bash
EXPOSE_ALLOWED_FRAME_ANCESTORS="https://www.example.com https://*.example.org"
```

They are added to the `frame-ancestors` directive of the Content Security Policy
(`expose/client/src/proxy.ts`, `src/lib/security/csp.ts`). This directive stays enforced while
the rest of the policy is only reported (`CSP_REPORT_ONLY=true`): a browser ignores
`frame-ancestors` in a report-only policy.

## Sign-in

Keycloak cannot be framed, and an embedded page has no storage in common with a top-level window
of the same site (storage partitioning). For a publication requiring authentication, the
embedded page does not redirect to Keycloak: its "Sign in" button opens a window,
`/auth/window`, which signs in, hands the tokens back to the frame by `postMessage`, and closes.
The browser must allow the popup.

## Cookies

The passwords, the share link tokens and the acceptance of the terms are kept in cookies set with
`SameSite=None; Secure`, so that they stay usable in a frame of another site: the client must be
served over HTTPS. Over HTTP, they fall back to `SameSite=Lax` and do not work in a frame of
another site. In a browser blocking the third party cookies, the embedded page cannot keep them
either.

## Indexing and analytics

`/embed/…` pages carry a `noindex` robots meta tag and are disallowed in `robots.txt`. See
[SEO and analytics](./seo-analytics.md).

In a frame, the Matomo cookie banner is never shown: the visits are measured without cookies,
unless the visitor already accepted them on the client (see
[Analytics (Matomo)](../Databox/analytics.md#cookie-consent)).

## Testing locally

`expose/iframe.html` is a dummy site framing the client:

1. Set `EXPOSE_ALLOWED_FRAME_ANCESTORS` to the origin that serves the file (for a file opened
   from disk, the browsers send no usable origin: serve it, e.g. with `python3 -m http.server`
   from `expose/`, and allow `http://localhost:8000`), then recreate the client
   (`dc up -d expose-client`).
2. Change the `src` of the iframe to the URL of a publication or of `/embed/{assetId}` of your
   stack.
3. Open the page in the browser.
