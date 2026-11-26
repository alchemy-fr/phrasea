# Brand

Source of the logos of the Phrasea apps. Every logo the apps serve — favicons,
manifest icons, Open Graph images, dashboard tiles — is generated from the SVG
files of this folder: change a logo here, then deploy it.

```bash
bin/dev/deploy-brand.sh            # every logo
bin/dev/deploy-brand.sh databox    # only the files of some logos
```

The command runs `pnpm brand` (`brand/deploy.mjs`, rendered by `sharp`) in the
`dev` container. It rewrites only the files whose content changes; commit the
generated files with the SVG: the Docker images copy them from each app.

## Logos

| File                                                 | App                                                    |
| ---------------------------------------------------- | ------------------------------------------------------ |
| `databox.svg`                                        | Databox                                                |
| `expose.svg`                                         | Expose                                                 |
| `uploader.svg`                                       | Uploader                                               |
| `dashboard.svg`                                      | Dashboard                                              |
| `parade.svg`, `consent.svg`, `lend.svg`, `order.svg` | Apps outside this repository, shown as dashboard tiles |
| `phrasea.svg`                                        | Phrasea itself: admin and emails                       |

## Generated files

For each Next.js client (`databox`, `expose`, `uploader`, `dashboard`), in
`<app>/client/public/`:

| File                                       | Content                                                               |
| ------------------------------------------ | --------------------------------------------------------------------- |
| `favicon.svg`                              | The SVG, 512 × 512                                                    |
| `favicon.ico`                              | 16, 32, 48 and 64 px                                                  |
| `apple-touch-icon.png`                     | 180 px: logo 140 px high on `#fafafa`, iOS showing transparency black |
| `icons/icon-192.png`, `icons/icon-512.png` | Manifest icons, transparent                                           |
| `icons/icon-maskable-512.png`              | Maskable manifest icon: logo 308 px high on `#fafafa`                 |
| `icons/og-image.png`                       | Open Graph / Twitter image, 1200 × 630: logo 420 px high on `#fafafa` |

For each API (`databox`, `expose`, `uploader`), in `<app>/api/public/`:
`favicon.ico`, `logo192.png` and `logo512.png` (manifest and admin).

In `dashboard/client/public/images/`: a copy of every SVG, for the service
tiles.

From `phrasea.svg`, the Phrasea logo of the admin and of the emails, 180 px,
transparent: `lib/php/admin-bundle/Resources/public/phrasea-logo.png` and
`lib/php/notifier-bundle/assets/email/phrasea-logo.png`. It stays a bitmap:
email clients do not show SVG images.

`#fafafa` is the `background_color` of the web manifest
(`lib/js/core/src/seo/manifest.ts`).
