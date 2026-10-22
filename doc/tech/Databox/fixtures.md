# Databox fixtures

`bin/dev/reset-db-fixtures.sh` empties the databases and loads the dev fixtures of databox.
They live in `databox/api/fixtures/`. The load chains three commands:

1. `hautelook:fixtures:load` persists the entities of the fixtures;
2. `app:workspace:ingest-assets` runs the ingest workflow on every asset: renditions,
   metadata read from the files, blurhash, watermark, embeddings (visual similarity) and
   faces;
3. `fos:elastica:populate` indexes the assets, with their renditions and embeddings.

The embeddings and the faces need the `similarity-embedder` and `face-recognition` services
(Compose profiles `similarity` and `face-recognition`, enabled by default in
`COMPOSE_PROFILES`). Without them, the jobs of these integrations fail without blocking the
rest of the ingest. In dev, the Messenger transports are synchronous: the whole workflow of
each asset runs inline, downloads and rendition-factory included, so the ingest takes a while.

| File                       | Content                                                        |
| -------------------------- | -------------------------------------------------------------- |
| `Common.yaml`              | the global Expose integration (`phrasea.expose`, no workspace) |
| `NorthbeamPress.yaml`      | **Northbeam Press**, a news agency                             |
| `LindenRowe.yaml`          | **Linden & Rowe**, a fashion brand                             |
| `FernhollowMuseum.yaml`    | **Fernhollow Natural History Museum**                          |
| `HollowtoneRecording.yaml` | **Hollowtone Recording**, a recording studio                   |

```bash
FIXTURES_GENERATE_IMAGES=true bin/dev/reset-db-fixtures.sh  # fast: plain coloured images
bin/dev/reset-db-fixtures.sh                                # real images (network required)
```

## Users

The users are created in Keycloak on the fly by `keycloakUser()` (`<username>@phrasea.local`,
temporary password `xxx`):

- `phrasea-admin` (realm role `phrasea-admin`): owner of the four workspaces;
- `alice`, `bob`, `carol` and `dave`: members of the four workspaces, with a different
  business role in each one.

To check what a persona sees, impersonate it from the Keycloak admin console.

## Workspaces

| Workspace                         | Slug                   | Locales    | Image theme | Assets |
| --------------------------------- | ---------------------- | ---------- | ----------- | ------ |
| Northbeam Press                   | `northbeam-press`      | en, fr     | `reportage` | 86     |
| Linden & Rowe                     | `linden-rowe`          | en, fr, it | `fashion`   | 80     |
| Fernhollow Natural History Museum | `fernhollow-museum`    | en, fr     | `museum`    | 85     |
| Hollowtone Recording              | `hollowtone-recording` | en, fr     | `studio`    | 80     |

All the audio and the video of the fixtures are in Hollowtone Recording: the other workspaces
hold images and PDF documents only.

The four workspaces are created through `App\Fixture\WorkspaceFactory`, like a workspace
created from the API: the "Public" attribute and rendition policies, the `main`, `preview`,
`thumbnail` and `animatedThumbnail` renditions, the "Name" attribute and the base
integrations come from `WorkspaceCreator`, and the fixtures get them back with the getters
of the factory.

Each workspace also has a restricted attribute policy and a restricted rendition policy
(with a rendition definition), granted to some personas only.

### Northbeam Press

News agency: editorial flow, geolocated, under embargo, multilingual.

- **Collections**: `Desks` (`World`, `Politics`, `Sports` > `Football` > `Premier League`,
  `World Cup`; `Tennis`; `Rugby`; `Culture` > `Film`, `Music`, owned by alice), `Archives`
  (`2024`, `2025`, `2026`), `Embargoed` (secret), `Wire Feed` (public in the workspace) and a
  collection named with special characters.
- **Assets**: photos, embargoed photos (`Embargo` tag, `Print hi-res` rendition), press
  releases and 10 "Photo essay" stories. Headlines and captions (en/fr) describe a scene in
  the city of the asset.
- **Attributes**: Headline (title), Story title (stories), Caption (translatable), Byline,
  Keywords (with rectangle, circle and target annotations), Location, City, Country, Date
  taken, Published at, Desk and Usage rights (entity lists), Embargo until, Word count, File
  size, Breaking, Watermark, Blurhash, People (faces). **Newsroom only** policy: Editor notes.
- **Rendition policy** `Print` (restricted): `Print hi-res`.
- **Tags**: Breaking, Exclusive, Embargo, Online, Offline.
- **Integrations**: ToastUI photo editor, Blurhash, watermark (Watermark attribute on the
  thumbnail), visual similarity, face recognition (into People).

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
- **Assets**: packshots by category, campaign photos, spec sheets and lookbooks (PDF), 6
  "Look" stories (a dress, a bag and a pair of shoes).
- **Attributes**: Product name (title), SKU, EAN, Colour, Sizes (multiple), Material,
  Gender, Season (entity lists, translated in fr and it), Launch date, Rights expiry, Model
  release signed, Photographer, Marketing copy (HTML, translatable), Models (faces).
  **Commercial** policy: Retail price, Wholesale price.
- **Rendition policy** `Studio` (restricted): `E-commerce zoom`.
- **Tags**: New in, Bestseller, Sale, Sustainable, Do not publish (all the FW26 assets).
- **Integrations**: ToastUI photo editor, Blurhash, visual similarity, face recognition (into
  Models), remove.bg (disabled), webhook to the PIM (disabled).

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
  accession records (PDF), 6 "Specimen record" stories (front, back and label views).
- **Attributes**: Common name (title, translatable), Scientific name, Inventory number,
  Family, Conservation status (IUCN, with emojis and colours) and Licence (entity lists),
  Collected on, Collector, Collection site, City, Country, Accession date, Keywords, On
  display, Credit line, People (faces). **Curatorial** policy: Condition report, Insurance
  value.
- **Rendition policy** `Archive` (restricted): `Archival master`.
- **Terms**: the workspace attaches its terms ("Fernhollow image licence terms", en/fr) to
  the exports.
- **Tags**: On display, In storage, On loan, Needs digitisation, Fragile.
- **Integrations**: Blurhash, watermark (Credit line on the preview), visual similarity, face
  recognition (into People), AWS Rekognition (disabled, labels into Keywords, dummy AWS
  secrets).

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

### Hollowtone Recording

Recording studio: music productions, recording sessions, voice-overs and jingles for
clients, masters reserved to the label. It holds all the audio and the video of the
fixtures.

- **Collections**: `Productions` > `Music` > `Albums`, `Music videos` (public in the
  workspace); `Client work` (owned by alice) > `Voice-overs & jingles` (public in the
  workspace); `Sessions` > `2025`, `2026`; `Vault` (secret); `Press` (public).
- **Assets**: album covers and session photos, tracks (4 per album: rock, jazz, electronic,
  folk), isolated takes of an instrument or a voice, voice-overs and jingles, music videos,
  session footage and behind-the-scenes videos (`Vault`); 4 "Album" stories (the cover and
  the 4 tracks) and 4 "Session" stories (a photo, a session footage and 2 takes). The titles
  of the albums, tracks, sessions, clips and voice-overs follow the number of the asset
  (`studioTitle(kind, n)`), so that they match the themed media.
- **Attributes**: Title (title, also on the "Session" stories), Release title ("Album"
  stories), Artist, Client, Genre and Instruments (entity lists), BPM, Key, ISRC, Recorded
  on, Engineer, Duration, Explicit, Keywords (cue annotations on the audio and the video,
  rectangles and circles on the session photos), Blurhash, People (faces). **Label** policy:
  WebVTT transcript (translatable), Contract notes.
- **Entity lists**: Genre and Instrument (translated in fr), Client (the artist of each
  album, 2 labels for the albums, an advertising agency and a radio for the voice-overs),
  People (faces).
- **Rendition policy** `Masters` (restricted): `Master` (Pick source).
- **Tags**: Rough mix, Final mix, Mastered, Approved by client, Do not distribute.
- **Integrations**: Blurhash, visual similarity, face recognition (into People), AWS
  Transcribe (disabled, transcript into WebVTT transcript, dummy AWS secrets).

| Persona | Role           | Rights                                                                   |
| ------- | -------------- | ------------------------------------------------------------------------ |
| alice   | studio manager | edit and operate the whole workspace; Label (edit); Masters              |
| bob     | sound engineer | create and edit in `Sessions`; reads Label; Masters                      |
| carol   | client (label) | `Client work` and `Albums` (plus `Music videos` and `Press`, visible to every member) |
| dave    | archivist      | view and edit `Vault`                                                    |

- Attribute filter rule: carol does not see the assets tagged `Do not distribute`.
- Asset policy "Unreleased masking": for carol and dave, the assets of `Albums` hide their
  `Master` rendition and their Contract notes.

## Writing fixtures

- Each file creates one workspace and prefixes its keys (`nb_`, `lr_`, `fh_`, `ht_`): all the
  files are loaded together, and a wildcard reference (`@nb_c_desk_*`) or a list fixture
  (`{@fh_a_v_s*}`) matches the keys of every file.
- The images of a workspace are numbered across the workspace (one range per group of
  assets): the number is the lock of `imageUrlRandomRatio()` (the same number gives the same
  picture) and links an asset to its source file (`'@fh_f_m<current()>'`).
- An asset with a file has a `source`; its renditions are not written in the fixtures, they
  are built by the ingest workflow run after the load (Pick source renditions link the
  source, the others are built by the rendition-factory).
- A story is represented by one of its items, its cover: the story shares the source file of
  that item (`source: '@nb_f_m<($current + 46)>'`), like `StoryCoverManager` does. Every
  story of the fixtures has a cover.
- The workspaces require the file analysis: two sources with the same content in a workspace
  are duplicates, and the ingest quarantines them (no renditions). Two locks below 210 give two
  different images (variant `lock % 42`, ratio `lock % 5`); with `FIXTURES_GENERATE_IMAGES`,
  the plain image takes a colour derived from the lock for the same reason.
- An asset has no name property: its title is the attribute with the highest `namePriority`
  (Headline, Product name, Common name, Title), or the "Name" attribute of `WorkspaceFactory`.
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

The `studio` theme of Hollowtone Recording (album covers, photos of recording studios and
sessions) is not uploaded yet: its images fall back to `nature`.

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

| Set                                      | Type | Files | Content                                                   |
| ---------------------------------------- | ---- | ----- | --------------------------------------------------------- |
| `northbeam-press/press-release`          | pdf  | 1-10  | press releases (titles of `ThematicFaker`)                |
| `linden-rowe/spec-sheet`                 | pdf  | 1-6   | technical specifications (tech packs) of SS26 styles      |
| `linden-rowe/lookbook`                   | pdf  | 1-4   | lookbooks (1-2 internal, 3-4 press kit, en and fr)        |
| `fernhollow-museum/accession-record`     | pdf  | 1-8   | accession records of specimens                            |
| `fernhollow-museum/field-notebook`       | pdf  | 1-7   | handwritten geology field notebooks                       |
| `hollowtone-recording/track`             | mp3  | 1-16  | music, 4 tracks per album: rock, jazz, electronic, folk   |
| `hollowtone-recording/take`              | mp3  | 1-8   | isolated takes: vocals, guitar, drums, piano, bass, strings, brass, synth |
| `hollowtone-recording/voice-over`        | mp3  | 1-6   | voice-overs and jingles (synthetic speech, en and fr)     |
| `hollowtone-recording/music-video`       | mp4  | 1-6   | music videos and filmed concerts                          |
| `hollowtone-recording/session-footage`   | mp4  | 1-8   | musicians recording in a studio                           |
| `hollowtone-recording/behind-the-scenes` | mp4  | 1-4   | mixing console, control room, session preparation         |

Each theme and set directory holds a `CREDITS.csv` (source, author and licence of every file):
the photos are CC0 or public domain, the videos are public domain or CC (BY, BY-SA), the PDFs
and the MP3s are generated (fictional content, speech synthesised with Piper voices under
CC0, CC-BY or public domain licences).
