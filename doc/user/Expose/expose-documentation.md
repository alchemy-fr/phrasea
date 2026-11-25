---
title: Expose User Guide
status: pending review
---
> **Status:** _Pending review_  
> This documentation page is waiting for a review and may be updated.

# Expose user guide

## What is Expose

Expose is the publication service of Phrasea: it shows sets of assets on the web, as galleries
or download pages, to anyone or to the people you choose. The assets come from Databox: you
publish a basket, or a saved search publishes its results for you. Expose then lets the editors
of a publication change how it is shown and who may see it.

This guide is for two readers: the **visitor**, who opens a publication, and the **editor**, who
publishes and manages publications. The administrators of the platform will find the technical
documentation in [Expose](../../tech/Expose/01_intro.md).

### Glossary

- **Publication**: a set of assets with a title, a description and settings, opened by its own
  web address.
- **Sub-publication**: a publication placed under another one (its parent). A sub-publication is
  hidden when its parent is, and protected by the password of its parent unless it has its own
  access settings.
- **Asset**: a file of a publication (image, video, audio, document), with its title and
  description.
- **Rendition**: another version of the file of an asset (preview, thumbnail…), offered for
  download in some layouts.
- **Profile**: a set of settings shared by several publications. A publication using a profile
  takes all its settings, except the ones it overrides.
- **Layout**: the way the assets of a publication are displayed (grid, gallery, download list).
- **Slug**: the short name of a publication in its web address, used instead of its
  identifier.
- **Share link**: a private link opening one publication to whoever holds it, even when the
  publication is disabled or protected.
- **Editor**: a user allowed to change a publication. **Operator**: an editor also allowed to
  manage its share links and its profile.

## Visitors

### Opening a publication

A publication opens from the link you were given:

- its own address, with its slug (`https://expose.example.com/my-gallery`) or its identifier;
- the address of one of its assets, which opens the publication with this asset displayed;
- a **share link** (`https://expose.example.com/s/…`), which opens the publication even when it
  is protected or not public. If the link has expired or has been revoked, the page says
  **Invalid link**.

The home page of Expose lists the publications that are publicly listed, unless the
administrators turned it off. A signed-in user also finds there the publications they can edit.

### Layouts

Each publication has one of three layouts:

- **Grid**: a mosaic of thumbnails. Clicking a thumbnail opens the asset in a full screen viewer.
- **Gallery**: one asset at a time in a large player, with **Previous** and **Next** buttons, its
  position, and the strip of the thumbnails below.
- **Download**: a list of files. Each asset shows its thumbnail, its title, its description and
  its size, with a **Download original** button and a **Download …** button for each of its
  renditions.

When a publication has no asset yet, the page says **No asset available**.

### Navigating

- Each asset has its own address: you can copy it from the address bar to point to that asset.
- In the viewer and in the gallery, the **←** and **→** keys show the previous and next assets
  (the list loops), and **Escape** closes the viewer (in the gallery, it goes back to the first
  asset).
- The **Back** button of the browser closes the viewer: moving from one asset to the next does
  not add a step to the history.
- In the grid, more thumbnails are loaded as you scroll down.

The title, the description and the date of the publication are shown at the top, with the
title, the description and the **Download** button of the asset displayed next to the player.
When a publication or an asset has a translation in the language of the interface, it is shown
in that language.

### Players

- **Images**: zoom in and out (buttons, mouse wheel or pinch), move the zoomed image, rotate it,
  fit it to the screen or show it at its actual size.
- **Videos**: the video player, with the **Subtitles** of the video when it has some, the ones of
  your language first.
- **Audio**: a player showing the waveform of the sound, with **Play** / **Pause**.
- **PDF documents**: the pages of the document, with **Previous page** / **Next page**, the page
  number, zoom, **Fit page** and **Fit width**.

Other files are shown by the icon of their type.

### Sub-publications

When a publication has sub-publications (or is one), the navigation drawer, opened from the
menu button of the top bar, shows its parent, the publication itself and its sub-publications.
A breadcrumb above the title leads back to the parent publications. Only the sub-publications
you are allowed to see are listed.

### Protected publications

A publication may be reserved:

- **Password**: the page shows **Protected publication** and asks for the password; type it and
  click **Enter**. The password is remembered by your browser, and also opens the
  sub-publications protected by the same password. After too many wrong passwords, the page says
  **Too many wrong passwords. Please try again in a few minutes.**
- **Sign in**: the page shows **Sign in required**. You are sent to the sign-in page, then back
  to the publication. If your account is not allowed to see it, the page says **Access denied**;
  **Sign in with another account** lets you change accounts.
- A publication that is disabled, not open yet or expired is not available: the page says
  **Forbidden** (or **Authentication required** when you are not signed in). Only its editors and
  the holders of a share link can open it.

### Terms

Some publications ask you to accept their **Terms and Conditions** before showing anything:
read them (or follow the **Terms** link), then click **Accept**. Your acceptance is remembered by
your browser for a year.

### Downloads

When the publication allows it, each asset has a **Download** button (and the **Download**
layout one button per file). Depending on the settings of the publication:

- the file is downloaded right away;
- **Download Terms and Conditions** are shown first: click **Accept & Download** (once for the
  publication);
- **Download via email**: type your email address and click **Request Download**. You receive a
  link by email, valid for 3 days.

A publication of several assets may offer **Download Archive**, a ZIP file of all its originals.
The archive is prepared on demand: a window says **The archive is being prepared. Its download
will start as soon as it is ready.** If it takes too long, click **Receive by email** to get its
link by email instead.

When downloads are disabled, no download button is shown.

### Theme, language and cookies

The **Settings** menu of the top bar offers:

- **Theme**: **Light**, **Dark** or **System**, a choice of color themes, and the theme of your
  organisation when it defines one;
- **Language**: English, French or German;
- **Cookies**: your choice about the audience measurement cookies (Matomo). Without your
  acceptance, the audience is measured without cookies.

## Editors

### Publishing a basket from Databox

Publications are created from Databox, not from Expose.

1. In Databox, open the basket: from the **Baskets** tab of the side panel, the menu of the
   basket then **Edit**, or the **Integrations** button of the basket view.
2. In the **Manage basket** window, go to the **Integrations** tab.
3. The first time, click **Authorize** under the Expose integration and sign in: Databox will
   publish with your Expose account.
4. Click **New publication**, fill in the form, then click **Create & sync**:
   - **Title**, **Slug** (optional) and **Description**;
   - **Parent publication (id)** and **Publication profile (id)**: the identifiers of an existing
     publication or profile, optional;
   - **Enabled**: whether the publication can be seen right away;
   - **Publish the whole files (ignore the regions of interest and the extracts)**: otherwise
     the files are cut to the region of interest set on the assets. This choice cannot be
     changed later.
5. Databox sends the assets of the basket, with their preview and thumbnail and a description
   made of their attributes. The progress is shown (**Sync in progress…**, then **Sync
   complete!**).

From then on, every change of the basket (assets added or removed) is sent to the publication.
The assets that cannot be published are listed (**… assets not published**) with the reason:
assets you cannot read, deleted, pending analysis or in quarantine, or refused by Expose.

Under the publication, in the same tab:

- the arrow icon opens the publication in Expose;
- **Force sync** sends the basket again;
- **Stop** ends the synchronization. Tick **Also delete the publication from Expose** to delete
  it; otherwise the publication and its assets stay on Expose, but the changes of the basket are
  no longer sent.

### Publishing a saved search automatically

An automation of a saved search can publish its results: in the **Automations** of the saved
search, create an automation (**New automation**), then **Add an action** and choose **Publish to
Expose**:

- **Expose integration**, then **Publication**: an existing publication you are an editor of, or
  a new one (**Create a publication**);
- **Publish the whole files**;
- **Mirror the results**: the assets that are no longer in the results are removed from the
  publication. Only with the scope **All results**.

The automation only touches the assets it published itself.

### Finding your publications

Signed in to Expose, the **Publications** entry of the top bar lists the publications you can
edit, sub-publications included, with a search field. The **Disabled** and **Unlisted** badges
show the publications that are not visible, or not on the home page. Each line can be opened,
edited or deleted.

On the page of a publication, an **Edit** button is shown to its editors, and a **Share** button
to its operators. A disabled publication shows a warning to its editors: **This publication is
currently disabled. Only administrators can see it.**

### The publication form

The **Edit** screen of a publication has:

- **Title** and **Slug** (used in the address of the publication instead of its identifier);
- **Parent publication**: to make it a sub-publication;
- **Profile**, with a link to **Edit profile**;
- **Cover**: see below.

Then the settings, in five sections:

- **Visibility**:
  - **Enabled**: whether the publication can be seen;
  - **Publicly listed**: listed on the home page, for everyone;
  - **Begins at** / **Expires at**: the period when it can be seen.
- **Download**:
  - **Download enabled**;
  - **Download via email**: the visitor types an email address and receives the download link
    there;
  - **Include the download terms in the archive**.
- **Layout**: **Grid**, **Gallery** or **Download**. **Map** is marked **Soon** and cannot be
  chosen.
- **Security**, **Access**:
  - **Public**: anyone with the link;
  - **Authentication**: the signed-in users allowed to view the publication;
  - **Password**: anyone who knows the password, for the publication and its sub-publications.
    The password is never shown again: leave it empty to keep it.
- **Terms**:
  - **Terms and conditions**: shown before the publication;
  - **Download terms**: shown before the first download.

  Each one with **Terms content** (HTML is accepted) and **Terms URL**.

Click **Save publication**.

### Profiles and overrides

A profile holds settings shared by the publications using it. The **Profiles** entry of the top
bar lists them (**Create profile**, edit, delete). Its form has a **Name** and the same settings
as a publication.

On a publication with a profile, each setting shows the value of the profile (**From the
profile:**) until you turn on **Override settings** for it. Changing a setting of the profile
changes every publication that does not override it. Choosing **Public** on a publication
overrides a protected profile.

A profile used by publications cannot be deleted (**A profile used by publications cannot be
deleted.**). Only the operators of a publication can change its profile.

### Cover

The cover is the image of the publication on the home page and in the link previews on social
networks and messaging apps. Click **Set cover image** in the form, then choose one of the
assets of the publication, or **No cover**: the first asset is then used.

### Share links

A share link opens the publication to whoever holds it, even when it is disabled or protected,
until it expires or is revoked. Its sub-publications are not shared.

1. Click **Share** on the page or the edit screen of the publication.
2. **Create link**, with an optional **Name** (recipient, purpose…) and an optional expiration
   (**Expires on**; without it, the link works until it is revoked).
3. **Copy link** and send it.

Each link can be renamed or extended, revoked (**Revoke**, for good: it no longer opens the
publication) or deleted from the list. A link holder is a visitor: the download settings and
terms apply to them.

### Deleting

**Delete** (in the list, or on the home page) deletes the publication, its assets and its
sub-publications. This cannot be undone. The assets stay in Databox. If the publication was
synchronized with a basket, stop the synchronization in Databox too.

### Who can do what

| Who                                    | Can                                                                                        |
| -------------------------------------- | ------------------------------------------------------------------------------------------ |
| Anyone                                 | see the enabled publications, within their dates, subject to their password or sign-in     |
| Holder of a share link                 | see that publication, whatever its state or protection                                    |
| Signed-in user with the view permission | see the publications reserved to signed-in users                                          |
| Editor                                 | change the publication, see it while disabled, download its files whatever its settings    |
| Operator                               | also manage its share links and change its profile                                         |
| Creator of the publication, administrator | everything, deletion included                                                           |

The permissions are granted by the administrators of Expose.

### What cannot be changed in Expose

Some things are not editable in the Expose interface:

- the **description** and the **translations** of a publication, its **CSS**, its **logo** and its
  **date**;
- the **assets** of a publication (title, description, order, files): they come from Databox;
- the **theme** of a publication: each visitor chooses their own theme;
- the **Map** layout, not available yet.

The assets change by changing the basket (or the saved search) in Databox. The other settings
are changed by the administrators of Expose.

## Embedding a publication or a media in a website

A publication, or one of its assets alone, can be shown in a page of another website with an
`iframe`. The administrators of Expose must first allow that website. There is no button
generating the code: ask them for the address to embed, or see the
[technical documentation](../../tech/Expose/iframe.md).

In an embedded page, signing in opens a separate window: allow the popups of the site.

## Questions and limits

- **I revoked a share link, but a download still works.** The download links already given stay
  valid until they expire (24 hours, 3 days for the links sent by email).
- **My publication cannot be opened by its slug.** The slugs `s`, `auth`, `embed`, `api`,
  `profiles` and `publications` are reserved by Expose: choose another one.
- **The map layout?** It is not available yet: a publication set to **Map** is shown as a grid.
- **The visitor accepted the terms on another computer.** The acceptance is remembered by each
  browser: the terms are shown again on another one.
- **The archive does not include a new asset.** The archive is rebuilt when the publication
  changes: ask for it again.
- **No archive button.** The archive is offered when downloads are enabled and the publication
  has more than one asset.
