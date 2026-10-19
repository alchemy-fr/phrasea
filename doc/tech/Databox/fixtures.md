# Databox fixtures

`bin/dev/reset-db-fixtures.sh` empties the databases and loads the dev fixtures of databox
(`hautelook:fixtures:load`, then `fos:elastica:populate`). They live in
`databox/api/fixtures/`:

| File                    | Content                                                        |
| ----------------------- | -------------------------------------------------------------- |
| `Common.yaml`           | the global Expose integration (`phrasea.expose`, no workspace) |
| `NorthbeamPress.yaml`   | **Northbeam Press**, a news agency                             |
| `LindenRowe.yaml`       | **Linden & Rowe**, a fashion brand                             |
| `FernhollowMuseum.yaml` | **Fernhollow Natural History Museum**                          |

```bash
FIXTURES_GENERATE_IMAGES=true bin/dev/reset-db-fixtures.sh  # fast: plain grey images
bin/dev/reset-db-fixtures.sh                                # real images (network required)
```

## Users

The users are created in Keycloak on the fly by `keycloakUser()` (`<username>@phrasea.local`,
temporary password `xxx`):

- `phrasea-admin` (realm role `phrasea-admin`): owner of the three workspaces;
- `alice`, `bob`, `carol` and `dave`: members of the three workspaces, with a different
  business role in each one.

To check what a persona sees, impersonate it from the Keycloak admin console.

## Workspaces

| Workspace                         | Slug                | Locales    | Image theme | Assets |
| --------------------------------- | ------------------- | ---------- | ----------- | ------ |
| Northbeam Press                   | `northbeam-press`   | en, fr     | `reportage` | 106    |
| Linden & Rowe                     | `linden-rowe`       | en, fr, it | `fashion`   | 86     |
| Fernhollow Natural History Museum | `fernhollow-museum` | en, fr     | `museum`    | 95     |

The three workspaces are created through `App\Fixture\WorkspaceFactory`, like a workspace
created from the API: the "Public" attribute and rendition policies, the `main`, `preview`,
`thumbnail` and `animatedThumbnail` renditions, the "Name" attribute and the base
integrations come from `WorkspaceCreator`, and the fixtures get them back with the getters
of the factory.

Each workspace also has a restricted attribute policy and a restricted rendition policy
(with a rendition definition), granted to some personas only.

### Northbeam Press

News agency: multimedia editorial flow, geolocated, under embargo, multilingual.

- **Collections**: `Desks` (`World`, `Politics`, `Sports` > `Football` > `Premier League`,
  `World Cup`; `Tennis`; `Rugby`; `Culture` > `Film`, `Music`, owned by alice), `Archives`
  (`2024`, `2025`, `2026`), `Embargoed` (secret), `Wire Feed` (public in the workspace) and a
  collection named with special characters.
- **Assets**: photos, embargoed photos (`Embargo` tag, `Print hi-res` rendition), videos,
  podcasts, press releases and 10 "Photo essay" stories. Headlines and captions (en/fr)
  describe a scene in the city of the asset.
- **Attributes**: Headline (title), Story title (stories), Caption (translatable), Byline,
  Keywords (with the 4 types of annotations), Location, City, Country, Date taken, Published
  at, Desk and Usage rights (entity lists), Embargo until, Word count, Duration, File size,
  Breaking, Watermark, Blurhash. **Newsroom only** policy: WebVTT transcript (translatable),
  Editor notes.
- **Rendition policy** `Print` (restricted): `Print hi-res`.
- **Tags**: Breaking, Exclusive, Embargo, Online, Offline.
- **Integrations**: ToastUI photo editor, Blurhash, watermark (Watermark attribute on the
  thumbnail), moderation, AWS Transcribe (disabled, dummy AWS secrets).

| Persona | Role             | Rights                                                                   |
| ------- | ---------------- | ------------------------------------------------------------------------ |
| alice   | editor-in-chief  | edit and operate the whole workspace; Newsroom only; Print               |
| bob     | photographer     | create and edit in `Desks`; reads Newsroom only; Print                   |
| carol   | freelancer       | `Sports` only (plus `Wire Feed`, public in the workspace)                |
| dave    | archivist        | view and edit `Archives`; Print                                          |

- Attribute filter rule: carol does not see the assets tagged `Embargo`.
- Asset policy "Embargo masking": for carol and dave, the assets of `Embargoed` (also filed
  in `Archives/2026`) hide their `Print hi-res` rendition and their Editor notes.

### Linden & Rowe

Ready-to-wear brand, PIM-like catalogue: seasons, products, confidential prices, public
press kit.

- **Collections**: `Seasons` > `SS26` (`Womenswear` > `Dresses`, `Knitwear`; `Menswear` >
  `Shirts`, `Outerwear`; `Accessories` > `Bags`, `Footwear`), `FW26 (unreleased)` (secret);
  `Campaigns` > `SS26 – Coastline`, `FW26 – Night Garden` (secret); `Lookbooks`; `Press Kit`
  (public); `Retired`.
- **Assets**: packshots by category, campaign photos, runway videos, spec sheets and
  lookbooks (PDF), 6 "Look" stories (a dress, a bag and a pair of shoes).
- **Attributes**: Product name (title), SKU, EAN, Colour, Sizes (multiple), Material,
  Gender, Season (entity lists, translated in fr and it), Launch date, Rights expiry, Model
  release signed, Photographer, Marketing copy (HTML, translatable). **Commercial** policy:
  Retail price, Wholesale price.
- **Rendition policy** `Studio` (restricted): `E-commerce zoom`.
- **Tags**: New in, Bestseller, Sale, Sustainable, Do not publish (all the FW26 assets).
- **Integrations**: ToastUI photo editor, Blurhash, remove.bg (disabled), webhook to the PIM
  (disabled).

| Persona | Role             | Rights                                                                   |
| ------- | ---------------- | ------------------------------------------------------------------------ |
| alice   | marketing        | view and edit `Campaigns`, `Lookbooks` and `Press Kit`; Commercial       |
| bob     | photo studio     | create and edit in `Seasons`; Studio (view and edit)                     |
| carol   | wholesale partner| `SS26` and `Press Kit` (plus `SS26 – Coastline` and `Lookbooks`, public in the workspace); Commercial; Studio |
| dave    | e-commerce       | view the whole workspace; Commercial; Studio                             |

- Attribute filter rule: carol does not see the assets tagged `Do not publish`.
- Asset policy "Press kit": for carol, the assets of `Press Kit` hide their prices and their
  `E-commerce zoom` rendition.

### Fernhollow Natural History Museum

Heritage inventory (specimens, field notebooks, loans). It is also the test bench of the
permissions: a collection tree of 5 levels, privacies 0 to 5 mixed on the branches,
collections owned by alice and bob, assets at every level of the tree.

- **Collections**: `Departments` > `Botany` > `Herbarium` > `Ferns` > `Rare Ferns`;
  `Entomology` > `Lepidoptera`, `Coleoptera`; `Ornithology`; `Geology` > `Minerals`;
  `Exhibitions` > `Wild Coasts (2025)`, `Under the Canopy (2026)`; `Conservation Lab`
  (secret); `Outgoing Loans`; `Open Collection` (public). The tree is drawn in a comment of
  the fixture file.
- **Assets**: specimens (scientific and common names of real species), field notebooks and
  accession records (PDF), audio guides, videos, 6 "Specimen record" stories (front, back
  and label views).
- **Attributes**: Common name (title, translatable), Scientific name, Inventory number,
  Family, Conservation status (IUCN, with emojis and colours) and Licence (entity lists),
  Collected on, Collector, Collection site, City, Country, Accession date, Keywords, On
  display, Credit line, Duration. **Curatorial** policy: Condition report, Insurance value.
- **Rendition policy** `Archive` (restricted): `Archival master`.
- **Terms**: the workspace attaches its terms ("Fernhollow image licence terms", en/fr) to
  the exports.
- **Tags**: On display, In storage, On loan, Needs digitisation, Fragile.
- **Integrations**: Blurhash, watermark (Credit line on the preview), AWS Rekognition
  (disabled, labels into Keywords, dummy AWS secrets).

| Persona | Role       | Rights                                                                   |
| ------- | ---------- | ------------------------------------------------------------------------ |
| alice   | curator    | view, edit and delete in `Departments`; Curatorial (edit); Archive       |
| bob     | researcher | view `Departments` (nothing on `Conservation Lab`)                       |
| carol   | visitor    | `Open Collection` (plus `Lepidoptera`, `Ornithology`, `Minerals` and `Exhibitions`, visible to every member) |
| dave    | registrar  | view and edit `Outgoing Loans`; Curatorial; Archive                      |

- Attribute filter rule: bob and carol do not see the critically endangered species
  (Conservation status = CR).
- Asset policy "Rare species location": for bob and carol, the assets of `Rare Ferns` hide
  their Collection site.
- Asset policy "Loans": for dave, the assets of `Outgoing Loans` hide their
  `Archival master` rendition.

## Writing fixtures

- Each file creates one workspace and prefixes its keys (`nb_`, `lr_`, `fh_`): all the files
  are loaded together, and a wildcard reference (`@nb_c_desk_*`) or a list fixture
  (`{@fh_a_v_s*}`) matches the keys of every file.
- The images of a workspace are numbered across the workspace (one range per group of
  assets): the number is the lock of `imageUrlRandomRatio()` (the same number gives the same
  picture) and links an asset to its files and renditions (`'@fh_f_m<current()>'`).
- An asset has no name property: its title is the attribute with the highest `namePriority`
  (Headline, Product name, Common name), or the "Name" attribute of `WorkspaceFactory`.
- The business values come from `App\Fixture\Faker\ThematicFaker`, which keeps the values
  of an asset consistent (`productName()` must come before the prices and the marketing
  copy, `specimenScientificName()` before `specimenCommonName()`); the AQL conditions of
  the attribute filter rules come from `App\Fixture\Faker\AqlConditionFaker`.
- A collection is at least as visible as its parent (its privacy and its permissions apply
  to its sub-collections), and an asset of privacy 2 or more is visible to every member of
  the workspace: to hide a branch from the members, keep its ancestors and its assets at
  privacy 0 or 1.
- The permissions (`AccessControlEntry`) use the masks of `alchemy/acl-bundle`
  (`PermissionInterface`); `objectType` is a string (`workspace`, `collection`,
  `attribute_policy`, `rendition_policy`).
- The asset policies match an asset by the ID of its reference collection (or of an
  ancestor); the reference collection is the first one added with
  `addToCollection(collection, true, true)`.

## Image themes

`imageUrlRandomRatio(workspaceId, lock, size, theme)` takes its pictures from the public
bucket of the fixtures:

```
s3://alchemy-phrasea-fixtures/fixtures/images/<theme>/<orientation>/<n>.jpg
https://alchemy-phrasea-fixtures.s3.eu-west-3.amazonaws.com/fixtures/images/<theme>/<orientation>/<n>.jpg
```

where `<orientation>` is `landscape` or `portrait` and `<n>` goes from 1 to 42. The lock
picks the variant and the ratio of the image, which is then cropped to the requested size.
The downloaded and cropped images are cached in `databox/api/var/fixtures/<theme>/`.

To add a theme:

1. Prepare 42 landscape and 42 portrait JPEG images (2000px on the long side, quality 65),
   named `1.jpg` to `42.jpg`.
2. Upload them, readable anonymously:
   ```bash
   aws s3 sync ./landscape s3://alchemy-phrasea-fixtures/fixtures/images/<theme>/landscape/
   aws s3 sync ./portrait s3://alchemy-phrasea-fixtures/fixtures/images/<theme>/portrait/
   ```
3. Use the theme in a fixture: `'<imageUrlRandomRatio(@w_x->id, <current()>, 1000, "<theme>")>'`.

`nature` is the fallback theme: an image that the requested theme does not have (HTTP 403 or
404) is replaced by the same variant of `nature`, with a warning in the logs, so that the
fixtures always load while a theme is incomplete.

The lock of an asset always gives the same variant (`lock % 42 + 1`) and the same orientation
(`lock % 5`: 0 to 2 landscape, 3 and 4 portrait), so a variant can be chosen for the assets that
use it: the `fashion` packshots show the category of their product (dress, knit, shirt…) and
the `museum` specimens their group (orchid, butterfly, owl, mineral…).

## Themed media

`randomMedia(workspaceId, type, n, set)` takes the PDF, MP3 and MP4 files of a set from the
same bucket:

```
s3://alchemy-phrasea-fixtures/fixtures/media/<workspace>/<kind>/<n>.<type>
```

where `<n>` is the number of the asset in its range (`<current()>`). A file missing from the set
(HTTP 403 or 404) is replaced by a generic media, with a warning in the logs; without `set`,
`randomMedia()` only picks generic media.

| Set                                  | Type | Files | Content                                               |
| ------------------------------------ | ---- | ----- | ----------------------------------------------------- |
| `northbeam-press/press-release`      | pdf  | 1-10  | press releases (titles of `ThematicFaker`)            |
| `northbeam-press/podcast`            | mp3  | 1-10  | news podcasts (synthetic speech, en and fr)           |
| `northbeam-press/video`              | mp4  | 1-10  | news footage                                          |
| `linden-rowe/spec-sheet`             | pdf  | 1-6   | technical specifications (tech packs) of SS26 styles  |
| `linden-rowe/lookbook`               | pdf  | 1-4   | lookbooks (1-2 internal, 3-4 press kit, en and fr)    |
| `linden-rowe/runway`                 | mp4  | 1-6   | runway videos                                         |
| `fernhollow-museum/accession-record` | pdf  | 1-8   | accession records of specimens                        |
| `fernhollow-museum/field-notebook`   | pdf  | 1-7   | handwritten geology field notebooks                   |
| `fernhollow-museum/audio-guide`      | mp3  | 1-6   | audio guides of the two exhibitions (en and fr)       |
| `fernhollow-museum/video`            | mp4  | 1-4   | exhibitions (1-2) and conservation lab (3-4)          |

Each theme and set directory holds a `CREDITS.csv` (source, author and licence of every file):
the photos are CC0 or public domain, the videos are public domain or CC (BY, BY-SA), the PDFs
and the MP3s are generated (fictional content, speech synthesised with Piper voices under
CC0, CC-BY or public domain licences).
