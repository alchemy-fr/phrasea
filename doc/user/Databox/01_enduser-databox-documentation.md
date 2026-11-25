---
title: 'Databox User Guide'
---

# Databox user guide

Databox is the media management application of Phrasea: it stores your assets (photos, videos, audio files, PDF documents and other files), describes them with attributes, and lets you search, edit, share, export and publish them.

This guide describes the Databox web client for its users. What you see depends on your permissions: an action you are not allowed to perform is hidden or grayed out. The administration screens (workspace settings, attributes, renditions, permissions, integrations, workflows…) are described in the technical documentation: see [Administration](../../tech/Databox/administration.md).

## The interface

The Databox window is made of a top bar, a side panel on the left and the results area.

### Top bar

From left to right, the top bar holds:

- the button that shows or hides the side panel;
- the logo, which brings you back to the search;
- the path of the current screen when a screen is open over the search (for example, the batch editor);
- **All services**, when your organization has configured it: the list of the other Phrasea applications;
- **Notifications** (bell icon), see [Following and notifications](#following-and-notifications);
- the settings menu (gear icon), see [Settings](#settings);
- your user menu: **My account** and **Sign out**. If you are not signed in, the **Sign in** button replaces it.

Two round buttons float over the window: **Add assets** (bottom right of the search screen) opens the upload dialog, and **Report an issue** (bottom left), when your organization has enabled it, sends a report to the support team.

### Side panel

The side panel has three tabs:

- **Facets**: the values found in the current results, to refine the search (see [Facets](#facets)).
- **Navigation**: the sections **Pinned stories** (when you have pinned at least one story), **Saved searches** and **Workspaces & collections**.
- **Baskets**: your baskets (see [Baskets](#baskets)).

In the **Navigation** tab, click the title of a section to collapse or expand it. To change the order of the sections, drag the handle that appears when you hover the title of a section (or focus it and use the ↑ and ↓ keys).

Drag the edge of the panel to resize it.

### Results area

Above the results, a toolbar shows:

- on the left, the checkbox that selects or unselects all the assets, the basket button (see [The current basket](#the-current-basket)) and, when assets are selected, the action buttons (see [Actions on assets](#actions-on-assets));
- on the right, the number of results (or of selected assets) and the **Display settings** button.

### Display settings

The **Display settings** button opens the following options:

- **Layout**: **Grid**, **Masonry**, **List** or **Map** (the map is only offered when your organization has configured it, see [Map view](#map-view)).
- **Thumbnail size**: grid, masonry and list.
- **Thumbnail fit**: **Whole image** or **Fill** (grid and list).
- **Display preview on hover**: shows a larger preview of the asset when the pointer rests on the file type chip at the bottom right of a thumbnail (click the chip to keep the preview open). With this option enabled, you also choose **Display file in preview**, **Display attributes in preview**, **Preview size** and **File / attributes ratio**.
- **Play media on hover**: plays videos and audio files on the thumbnails.
- **Auto play media in preview**.

These settings are saved in your preferences, and in your display profile when it is synchronized (see [Display profiles and pinned filters](#display-profiles-and-pinned-filters)).

## Workspaces and collections

### Definitions

**Workspace**: a space that contains collections and assets. Its settings (languages, attributes, entity lists, renditions, permissions…) apply to everything it contains. An asset always belongs to one workspace.

**Collection**: a folder of assets inside a workspace. Collections are hierarchical: a collection can contain sub-collections. Each collection has its own privacy and permissions.

An asset has a reference collection, and can also appear in other collections of the same workspace as a shortcut (see [Moving and copying](#moving-and-copying)).

### Browsing the tree

The **Workspaces & collections** section of the **Navigation** tab shows the workspaces and collections you can access. Click the arrow of a workspace or of a collection to expand it.

- Click the name of a workspace or of a collection to show its assets: the search is limited to it.
- Use the **Search collections…** field to find a collection by its name.
- A filled folder icon indicates a collection that is not public; a small group icon indicates a shared collection; a struck-through name indicates a collection in the trash.

At the bottom of the section, **Trash** shows the deleted assets (see [Deleting and the trash](#deleting-and-the-trash)) and **Quarantine** opens the assets awaiting review (see [Quarantine](#quarantine)).

The ⋮ button that appears when you hover a workspace, or a right-click on it, opens its menu: **Pin**, **Add collection**, **Add asset**, **Import assets…** and **Manage workspace**. The menu of a collection holds **Pin**, **Notifications**, **Add asset**, **Import assets…**, **Create sub-collection**, **Edit** and **Delete** (or **Restore** for a collection in the trash).

### Creating a collection

1. Open the menu of the workspace and choose **Add collection**, or open the menu of a collection and choose **Create sub-collection**.
2. In the **New collection** window, enter the name and choose the privacy.
3. Click **Create**.

You can also create a collection on the fly from the destination picker of the upload, move, copy and save dialogs: click **New collection** under the workspace or the collection where you want it, type the name and press Enter.

### Managing a collection

Choose **Edit** in the menu of a collection to open the **Manage collection** window. Its tabs are:

- **Info**: identifier, owner, dates, privacy, workspace and path.
- **Edit**: the name and the privacy. The name can be translated: the **Default** tab holds the main name, and each language of the workspace has its own tab.
- **Notifications**: the events of the collection that you follow (see [Following and notifications](#following-and-notifications)).
- **Permissions**: the users and groups allowed on the collection, and the permissions inherited from its parent.
- **Operations**: **Move collection**, to choose its new parent in the same workspace, and the **Danger zone**, to delete it.

You can also move a collection by dragging it onto another collection or onto a workspace (to move it to the root).

When you delete a collection, it goes to the trash with its assets: type its name to confirm. To restore it, choose **Restore** in its menu.

### Pinning a workspace or a collection

**Pin** in the menu of a workspace or of a collection creates a saved search limited to it, listed in **Saved searches**: one click then brings you back to it.

## Adding assets

### Opening the upload dialog

The **Add assets** window opens:

- with the **Add assets** button (+ icon) at the bottom right of the search screen;
- with **Add asset** in the menu of a workspace or of a collection, which preselects it as the destination;
- by dropping files from your computer anywhere on the search screen;
- by pasting a file or an image (⌘V on macOS, Ctrl+V elsewhere) when the cursor is not in a text field.

When the search is limited to a workspace or a collection, it is preselected as the destination.

### Uploading files

1. In the **Files** tab, drop your files or click to browse your computer. **Add more** adds other files, and the cross of a file removes it.
2. Under **Destination**, choose the workspace or the collection. You can create a collection on the fly with **New collection**.
3. Choose the **Privacy** (see [Privacy](#privacy)).
4. Fill in the **Attributes** if you want to (see [Pre-filling attributes](#pre-filling-attributes)).
5. Click the **Upload** button, which shows the number of files.

The window closes right away and the files are sent in the background: a progress message shows at the bottom of the window (see [Pending uploads](#pending-uploads)).

The **Quiet (no notification, no webhook)** option creates the assets without notifying the users who follow the destination and without calling the webhooks of the workspace.

### Adding files from URLs

In the **URLs** tab, enter one URL per line (starting with `http://` or `https://`). With **Import the files** checked (the default), the files are copied into Databox; unchecked, the assets reference the remote URLs.

### Creating an asset without a file

In the **No file** tab, enter the **Asset title** and click **Create the asset**. The asset is created without a file: you can add one later from its viewer, with **Add a source file**.

### Uploading as a story

Check **Create a story with these files** to group the uploaded files in a new story (see [Stories](#stories)):

1. Enter the **Story title**.
2. Choose the **Story cover** among the files, or click **Use as story cover** next to a file.
3. Fill in the attributes of the story if needed.

In the **No file** tab, **Create a story** creates an empty story, to which you can add assets later.

### Privacy

The privacy decides who can see an asset or a collection:

- **Secret**: only the owner and the users who have been granted access.
- **Private (users can request access)**: the users see that it exists, but not its content, unless they have been granted access.
- **Public**: its content is open.

For **Private** and **Public**, **Only visible to workspace users** limits this to the users of the workspace. For **Public**, **User must be authenticated** opens the content to signed-in users only; without it, anonymous visitors see it too.

On upload, **Not set (inherit)** (the default) applies the privacy of the destination. A collection or an asset is always at least as open as its parent collection: the levels more restrictive than the parent are disabled, and a message reminds you of the inherited privacy.

### Pre-filling attributes

The **Attributes** section of the upload dialog shows the attributes of the destination workspace: the values you enter apply to all the uploaded files. Translatable attributes have a tab per language of the workspace, so that you can fill in several languages at once.

### Value templates

A value template stores attribute values, and optionally a privacy, to reuse them on the next uploads.

To create a template:

1. Fill in the attributes and the privacy in the upload dialog.
2. Open **Save as template** and enter the **Template name**.
3. Choose its options:
    - **Remember collection**: the template is offered for the destination collection only; **Including sub-collections** also offers it in its sub-collections. Without this option, **In every collection** offers it in the whole workspace.
    - **Attributes** and **Privacy**: what the template keeps.
    - **Public**: shares the template with the other users.
    - **Replace the applied template**: when a template is applied, updates it instead of creating a new one.
4. Click **Save template**.

On the next uploads, the **Value templates** field shows the templates available for the destination: choose one or more templates in **Apply a template…** to fill in the form.

### Pending uploads

During an upload, a message at the bottom of the window shows the progress. Click it to open **Pending uploads**: the progress of each file, the files that have been sent and the failed ones. The cross of a file cancels its upload, and **Clear finished** removes the finished uploads from the list.

### Importing from a JSON or NDJSON file

To create many assets at once from a file describing them (source URLs, collections, attributes…):

1. Choose **Import assets…** in the menu of a workspace or of a collection.
2. Drop the JSON or NDJSON file in **Instructions file**: a preview shows the number of entries and those that will be rejected. **File format** describes the expected fields.
3. Check the **Format**, the **Destination** and the **Privacy**.
4. Click **Import**.

The import runs in the background. Follow its progress with **View imports**, or in the **Imports** tab of **Manage workspace**, which lists the imports with their status, their counts and their rejected entries.

### Phrasea Uploader

Besides Databox, files can be sent with Phrasea Uploader, an application dedicated to uploads (forms, upload links for people without an account…): see the [Uploader user guide](../Uploader/uploader-documentation.md).

## Searching

### Text search and suggestions

Type one or more words in the **Search assets…** field and press Enter (or click **Search**). The words found in the attributes are highlighted in the results. The cross of the field clears the text.

While you type, suggestions appear (use ↑ and ↓, then Enter, or click):

- assets, collections and workspaces whose name matches: a collection or a workspace limits the search to it;
- **Filter by** a field, for a single word matching the name of an attribute or of a built-in field (type `@` to list the built-in fields): the field is written in the search bar as `field:`, and its values are then suggested;
- the values of the chosen field: picking one adds a condition.

The button with a crosshair icon, **Search around me**, adds your position to the search (your browser asks for your permission).

The **More** menu of the search bar holds **Clear search** (text, conditions and sort), **Save search** or, for a saved search, **Update search** and **Save as new search** (see [Saved searches](#saved-searches)).

### Conditions

Conditions filter the results on a field. They are shown as chips under the search bar.

- Click the text of a chip to edit the condition.
- Its ⋮ button (or a right-click) offers **Disable** or **Enable**, **Invert (NOT)** (or **Cancel the inversion**), **Edit** and **Remove**. An inverted condition is shown in red, a disabled one is struck through.

To add a condition:

1. Click **Add condition**.
2. In the **Builder** tab, choose a field in **Select a field…** (**Built-in** fields such as the collection, the dates, the file type or the privacy, or your **Attributes**), then an operator (**Is**, **Is any of**, **Contains**, **Starts with**, **Is before**, **Is empty**… depending on the type of the field) and a value.
3. Add other conditions with **Add condition**, or groups of conditions with **Add condition group**. Each group matches **All (AND)** or **Any (OR)** of its conditions.
4. Click **Add**.

**Preview AQL** shows the query written by the builder. The **AQL** tab lets you write it directly. The **Invert (NOT)** switch inverts the whole condition.

Pinned filters can also appear before the chips (see [Display profiles and pinned filters](#display-profiles-and-pinned-filters)).

### Facets

The **Facets** tab lists, for the current results, the values of the fields configured as facets, with the number of assets for each value.

- Check one or more values to keep only the matching assets (several values of the same facet are combined with OR). **Missing** keeps the assets without a value.
- For a date, move the handles of the slider to choose a period; **Clear filter** removes it.
- The buttons that appear when you hover the title of a facet pin it to the top (**Pin to top**) or hide it (**Hide facet**).

A checked value adds a condition, which you can then invert or disable like any other condition.

The ⋮ menu of the tab holds **Expand all**, **Collapse all** and **Facet settings**: drag the facets to reorder them, hide or show them (hidden facets are listed under **Hidden**), or click **Reset to default**.

The facets themselves are chosen by the workspace administrators.

### Sorting

Click **Sort** to open **Sort by**:

1. Enable the fields to sort on with their switch, or add an attribute with **Add a sort…**.
2. Drag the fields to choose their order, and click the arrow of a field to choose the direction.
3. Check **Group by sections** to separate the results according to the first field (not available when sorting by score).
4. Click **Apply**. **Reset** restores the default sort.

For example, sort on a "Country" attribute and then on a "City" attribute, and group by sections: the results are listed by country, then by city, with a separator for each country.

### Saved searches

A saved search keeps the text, the conditions and the sort of a search.

1. Open the **More** menu of the search bar and choose **Save search**.
2. Enter the name and choose the privacy: **Secret** (only you and the users you allow), **Private** (accessible by link, not listed) or **Public** (listed for everyone). Publishing a search requires a specific permission.
3. Click **Save**.

Saved searches are listed in the **Saved searches** section of the **Navigation** tab: click one to run it. After changing it, **Update search** saves the changes, and **Save as new search** creates another one.

The ⋮ menu of a saved search holds **Edit**, which opens **Manage saved search** (tabs **Info**, **Edit**, **Permissions** and **Automations**), and **Delete**.

Automations apply actions to the results of a saved search, on demand or on a schedule: adding to a collection, changing an attribute, sharing, publishing to Expose… Each automation of a saved search appears in its menu, with **Run now** and **Dry run**.

### Display profiles and pinned filters

A display profile stores how Databox is displayed: the attributes shown in the asset panel, the attributes shown on the thumbnails of the grid, the search filters pinned above the results, and your display preferences (layout, facets, sections of the side panel, tabs of the asset panel, theme…).

Profiles are chosen in the settings menu, under **Display profile**:

- **Switch profile** lists the default profile (**Default display profile**) and the profiles you created or that were shared with you.
- **Auto-sync preferences** saves your changes in the profile as you make them; without it, **Save current preferences to profile** saves them on demand.
- **New profile…** creates a profile, and **Manage profiles…** lists them.

The profile window has the tabs **General** (name and description), **Asset attributes** (the attributes shown in the asset panel, in order, with dividers), **Grid card** (the values shown over or under the thumbnails), **Search filters** and **Permissions** (**Public** to share the profile with every user).

You can also add an attribute to your profile from the asset panel, with **Pin to profile**.

The pinned search filters appear before the conditions, above the results. Click one to choose its value (a list, radio buttons, or **From** and **To** dates for a date), then click **Apply**. **Clear** removes the value.

### Map view

Choose **Map** in **Display settings** to place the results on a map, by their geolocation attribute (an attribute of type "geo point"). Nearby assets are grouped in bubbles that show their number; click a bubble to see them and zoom in, and click an asset to open it.

- **Draw a zone**: click and drag on the map to search within a circle (Esc cancels). Drag the zone to move it, or its edge to resize it; **Clear zone** removes it.
- **Search this area**, in the bubble of a group, limits the search to its area; **Clear area** removes this limit.
- **Show all the results** centers the map on the results, and **Satellite view** switches the background.

When the workspaces have several geolocation attributes, choose the one to use at the top left of the map.

### Similarity search

When similarity is enabled for the workspace, **Find similar**, in the menu of an asset, runs a search of the visually similar assets. Each result shows its **Similarity** as a percentage. The **Similar** tab of the asset viewer shows the most similar assets, and **Search similar assets** opens them in the search.

### Faces

When the face recognition integration is enabled, its tab in the asset viewer lists the faces of the image. Click **Detect faces** to analyze it.

- To name an **Unnamed** face, type a name in its **Who is this?** field, then choose an existing person or create a new one.
- A **Recognized** face has been named automatically from another face; **Confirm the person** makes it a reference.
- **Forget this face** removes it.

The persons are stored in an attribute of the asset: search a person through the facet or a condition on this attribute.

## Viewing an asset

### Opening the viewer

Double-click an asset, or choose **Open** in its menu. The viewer shows the file on the left and a panel on the right.

- **Previous** and **Next** (or the ← and → keys) browse the results. In a story, they browse its items: at the end of the story, press again to go to the next asset.
- The list at the top lets you display another rendition.
- When the asset has a region of interest, **Show the whole asset** and **Show the region of interest** switch the display.
- The buttons of the header download, edit, share or delete the asset; its ⋮ menu holds the other actions (see [Actions on assets](#actions-on-assets)). **Follow** subscribes you to the asset (see [Following and notifications](#following-and-notifications)).
- **Hide panel** gives all the space to the file. The close button (or Esc) closes the viewer.

Images can be zoomed with the wheel; a double-click switches between the whole image and its actual size. For an asset without a file, **Add a source file** lets you add one.

### Tabs of the panel

The panel has the following tabs, depending on your permissions and on the configuration of the workspace:

- **Info**: attributes and information (see below).
- **Discussion**: messages and annotations (see [Discussing](#discussing)).
- **Renditions**: the renditions of the file.
- **Similar**: the similar assets (see [Similarity search](#similarity-search)).
- one tab per integration available for the file (for example the photo editor or the face recognition);
- **Versions**: the previous source files.
- **Permissions**: who is allowed on the asset.
- **Workflow**: the processing runs of the asset.
- **Operations**: location, shortcuts and deletion.

The tabs that do not fit are in the ⋮ menu of the tab bar. **Reorder tabs**, in this menu, lets you drag the tabs to change their order (**Reset to default** restores it).

### Info tab

The **Info** tab is made of sections:

- **Attributes**: the values of the attributes. When you hover an attribute, buttons let you change its display format (**Change format**), copy its value, or pin it to your profile (**Pin to profile**).
- **Information**: owner, creation and modification dates, privacy, workspace, reference collection, source file and type.
- **Collections** and **Stories**: the collections and the stories the asset belongs to; click one to open it in the search.
- **Metrics**: the audience of the asset (views, plays…), when your organization measures it.
- **Attachments**: files attached to the asset. **Add attachment** adds a file or a URL; the ⋮ menu of an attachment downloads it, renames it (**Rename**), detaches it (**Detach**, the attached asset is kept) or deletes it.

### Renditions tab

The renditions are the versions of the file generated for display and download (thumbnail, preview, other formats…). The tab shows each rendition with its type and size; click a rendition to display it in the viewer. Badges indicate a rendition being generated (**Generating…**), **Locked**, **Substituted** or **Outdated**.

- **Download** and **Save as…** apply to the rendition.
- **Substitute** replaces the file of a rendition with a file of yours, and **Upload** provides a rendition not generated yet (for the renditions that allow it).
- **Create custom rendition** (images) creates a new rendition: choose the **Source**, the **Aspect ratio** and the **Zoom** (drag the image to frame it), the **Max width** and **Max height**, the **Format**, **Black & white** and **Write attributes as file metadata**, then click **Create**.

### Versions tab

When the source file is replaced, the previous file is kept as a version. The tab lists the versions with their date, type and size; each one can be downloaded, deleted, or saved with **Save as…**. To restore a version, choose **Save as…** and then **Replace source file of this asset**.

### Workflow tab

Workflows run the processing of the assets (analysis, rendition generation, integrations…). The tab lists the runs with their status; **View** opens the detail of a run, where a job can be run again with **Rerun**. **Trigger workflow again** runs the workflow of the asset again.

While a run is in progress, a card over the file shows its progress; **Open the run** opens it.

### Operations tab

- **Location**: the workspace and the reference collection of the asset.
- **Shortcuts**: the other collections where the asset appears; **Remove** removes it from one of them.
- **Danger zone**: delete (or restore) the asset; type its name to confirm.

### Integrations

Depending on the configuration of the workspace, other tabs bring integrations, for example:

- the photo editor: **Open photo editor** opens the image in an editor; **Save as** saves the result, which you can then reopen from the tab;
- the face recognition (see [Faces](#faces)) or the detection of labels and texts.

## Editing attributes

### Editing one asset

1. Open the asset and click the edit button (pencil) of the header, or choose **Edit** in the menu of the asset.
2. The panel switches to edit mode: change the privacy and the values of the attributes. A translatable attribute has a tab per language; a multi-valued attribute has a button to add a value and a button to remove each one.
3. Click **Save**. **Reset** cancels your changes, and **Close editor** leaves the edit mode.

Attributes that you cannot edit are marked "read only".

### Batch editing

1. Select several assets of the same workspace.
2. Click **Edit attributes**. The batch editor opens with the selected assets at the top.
3. In the list of attributes, choose an attribute (Tab and Shift+Tab move to the next and previous one).
4. Enter the value. It applies to the assets selected at the top: click a thumbnail to work on it alone, ⌘-click on macOS (Ctrl-click elsewhere) or Shift-click to select several.
    - For a single-valued attribute, **Indeterminate** indicates that the assets have different values: a typed value applies to all of them.
    - For a multi-valued attribute, each value shows the share of assets that have it, with a button to add it to all the selected assets and a button to remove it; **Add to all** adds a new value.
5. On the right, the **Values** tab lists the values in use (**Apply to selected**, **Select assets**), and the **Preview** tab shows the result asset by asset.
6. Click **Save**, check the summary in **Confirm changes?**, then confirm.

**Undo** and **Redo** cancel or repeat your last changes before saving.

## Actions on assets

### Selecting assets

- Click an asset to select it; ⌘-click on macOS (Ctrl-click elsewhere) adds or removes an asset; Shift-click selects a range.
- ⌘A on macOS (Ctrl+A elsewhere) selects all the loaded assets, and Esc clears the selection.

### Action bar and menus

When assets are selected, the toolbar shows the actions available for them: **Download** (one asset) or **Export** (several), **Edit** or **Edit attributes**, **Share**, **Move**, **Copy**, **Create a story…**, and **Delete**. In the trash, **Restore** and **Delete permanently** replace them.

The ⋮ button of a thumbnail, or a right-click on it, opens the menu of the asset (or of the whole selection, if the asset is selected). It holds, besides the actions above:

- **Open** and **Info**, which open the viewer;
- **Find similar** (see [Similarity search](#similarity-search));
- **Pin story** for a story (see [Stories](#stories));
- **Add to basket**;
- **Save as…** and **Replace source file** (see [Replacing the source file](#replacing-the-source-file));
- **Edit the region of interest** (see [Region of interest](#region-of-interest)).

### Drag and drop

Drag assets (or the selection) onto a collection or a workspace of the **Navigation** tab, onto a basket of the **Baskets** tab, or onto a pinned story. While you drag, the side panel switches to the **Navigation** tab; hover another tab to open it, or a collapsed workspace or collection to expand it. The label that follows the pointer tells what the drop will do.

| Keys held | On a collection or a workspace | On a story |
|---|---|---|
| none | Move | Add |
| ⌘ on macOS, Ctrl elsewhere | Add (shortcut) | Move |
| ⇧⌘ on macOS, Ctrl+Shift elsewhere | Duplicate | Duplicate |

A drop on a basket always adds the assets. A drop into another workspace always duplicates them, after a confirmation.

### Exporting and downloading

1. Select the assets and click **Export** (or **Download** for a single asset).
2. Check the renditions to include in the export.
3. When assets have a region of interest or a basket extract, the exported files are cut and cropped: check **Export the whole files (ignore the regions of interest and the extracts)** to export them whole.
4. If a workspace requires the acceptance of its terms and conditions, read and accept them.
5. Click **Export**.

A message shows the preparation of the export, then **Export ready!** with a **Download** button.

### Moving and copying

**Move** changes the collection of the assets, within their workspace. Choose the **Destination**, then click **Move**.

**Copy** adds the assets to another collection:

- with **Copy by reference (shortcut)** (the default), the same asset appears in the destination collection: a change of the asset shows in both collections;
- without it, the asset is duplicated: a new asset is created, with its own attributes if **Copy attributes** is checked.

Copying into another workspace always duplicates the assets.

### Replacing the source file

**Replace source file** replaces the file of the asset with a new file, uploaded or given by its URL. The previous file is kept in the **Versions** tab and the renditions are generated again.

**Save as…** saves the source file (or a rendition, or a version) as:

- **New asset**: a copy of the file in a new asset, in the collection of your choice;
- **Rendition of this asset**: the file of one of its renditions;
- **Replace source file of this asset**: the new source file of the asset.

### Region of interest

**Edit the region of interest** (images, videos and audio files) defines the part of the asset to show and to use: a crop rectangle and, for videos and audio files, time ranges. The file is not modified: the region of interest is shown by default, and applied when the asset is published or exported.

- **Crop**: draw the rectangle on the media, then move or resize it by its corners; choose a ratio (**Free**, **Original**, 1:1, 4:3…).
- **Time ranges**: drag on the timeline to add a range, or click **Add a range**; drag its edges, or use **Start at the current position** and **End at the current position**. The ranges are played one after the other.

**Remove the region of interest** deletes it.

### Deleting and the trash

1. Select the assets and click **Delete**.
2. If the assets are in collections, choose **Move to trash**, or **Only remove from collections** and check the collections to remove them from.
3. Confirm.

The deleted assets go to the trash. Click **Trash** at the bottom of the **Workspaces & collections** section to see them. There, **Restore** puts them back in their collections (if their collection is also deleted, restore it first), and **Delete permanently** deletes them for good: type "Delete" to confirm.

### Sharing

**Share** creates public links to one or more assets of the same workspace.

- Enable **Create a public link**, then click **Copy link**. For a single asset, choose whether the link opens the asset page or a given rendition. The buttons below share the link on social networks, and **Embed code** gives the code to insert the asset in a web page.
- To revoke the link, disable **Create a public link**.

**Advanced: multiple links with audience and dates** lets you create several links, for example one per recipient:

1. Click **Create new share link**.
2. Fill in **Name / audience**, and optionally **Starts at** and **Expires at**.
3. Check the **Shared renditions**: only these renditions can be viewed and downloaded from the link.
4. Click **Create**.

Each link can then be edited (name and dates), embedded (**Embed code**) or revoked (**Revoke**).

The people who open a link see the shared assets, can browse them and download the shared renditions (**Download…**, **Download all…**). If the workspace has terms and conditions, they must accept them first.

## Stories

A story is an asset that groups other assets in a given order, for example the pages of a report or the photos of an event. It has its own attributes and a cover.

To create a story:

1. Select the assets of the same workspace and choose **Create a story…**.
2. Enter the **Story name** and choose the **Story cover**.
3. Confirm.

You can also create a story while uploading (see [Uploading as a story](#uploading-as-a-story)), or add assets to a story by dragging them onto it (see [Drag and drop](#drag-and-drop)).

Open a story to see its items in a strip under the file; click an item to show it. If you can edit the story, drag the items to reorder them, and right-click an item to **Mark as cover**, **Move to start**, **Move to end** or **Remove from story**.

**Pin story**, in the menu of a story, adds it to the **Pinned stories** section of the **Navigation** tab; **Unpin story** removes it.

## Collaboration

### Annotating

Annotations draw shapes and texts on an image, a video or a PDF, to point at a precise area.

1. Open the asset, then the **Discussion** tab, and click **Annotate the asset**.
2. In the annotation toolbar, choose a tool: **Select**, **Rectangle**, **Circle**, **Face**, **Arrow**, **Line**, **Target**, **Text** or **Drawing**. For a video, **Add a cue at the current time** marks a moment.
3. Choose the **Color**, the **Fill** and the **Size** (or the **Font size** of a text), then draw on the media.
4. Select a shape to move it, resize it, rename it (**Rename**), duplicate it (**Duplicate**) or delete it.
5. Write your message and send it: the annotations are attached to it.

In a message, click an annotation to show it on the media. Some attributes also store annotations: their annotate button is in the edit mode of the panel. **Hide annotations** hides the shapes, and **Show the annotations of the asset** shows them again.

### Discussing

The **Discussion** tab of the viewer gathers the messages about the asset. Type in **Write a message…** and press Enter to send (Shift+Enter for a new line).

- **Mention someone** (or type `@`) notifies a user.
- **Attach files** adds files (you can also drop or paste them), **Emoji** inserts an emoji, and **Show formatting** shows the formatting toolbar (bold, italic, link, lists, quote, code…).
- **Reply** answers a message; the ⋮ menu of your messages edits or deletes them.

The messages are visible to the users who can access the asset.

### Following and notifications

**Follow**, in the header of the asset viewer, subscribes you to the asset; its arrow lets you choose the topics: **Updates**, **Deletion** and **New comments**. **Unfollow** ends the subscription.

To follow a collection, choose **Notifications** in its menu, then choose the topics: **Asset added**, **Asset removed**, **Asset updated** and **New comment**.

The **Notifications** bell of the top bar lists your notifications; click one to open what it is about. **Mark all as read** marks them all as read.

## Baskets

A basket is a selection of assets, kept temporarily or permanently, to work on them together: export them, share them, publish them in a web gallery… The same asset can appear several times in a basket.

### Accessing baskets

The **Baskets** tab of the side panel lists your baskets, with their number of items. Click a basket to open it. **Display archived** also shows the archived baskets.

### Creating a basket

1. Click **Create basket** (+ button of the **Baskets** tab).
2. Enter a name and, optionally, a description.
3. Click **Save**.

### The current basket

The current basket receives the assets you add. The basket button of the results toolbar shows its name and its number of items:

- with assets selected, the button adds them to the current basket (**Add selection to basket**);
- otherwise, it opens the current basket (**Open basket**).

The arrow of the button (**Switch basket**) lists your baskets, to choose another current basket. You can also choose **Set as current** in the menu of a basket, or double-click it in the **Baskets** tab. A new basket becomes the current basket.

### Adding assets to a basket

- Select assets and click the basket button of the toolbar.
- Choose **Add to basket** in the menu of an asset.
- Drag the assets onto a basket of the **Baskets** tab.

### Working in a basket

The basket view shows its items in order, with their position. Select items to use the actions on assets (export, share…); **Remove from basket** removes them from the basket without deleting them.

The ⋮ menu of a basket, in the **Baskets** tab, holds **Set as current**, **Edit**, **Archive** (or **Unarchive**) and **Delete**. An archived basket is hidden from the list.

### Cutting and cropping basket items

**Cut / crop**, on an item of the basket (images, videos and audio files), defines an extract for this item only: a crop rectangle and, for videos and audio files, time ranges (as for the [region of interest](#region-of-interest)). The file is not modified: the extract applies when the item is played, published or exported, and replaces the region of interest of the asset. **Remove extract** deletes it.

A badge on the item shows its extract, or the region of interest it inherits from the asset.

### Sharing baskets

You can share a basket with other users of Databox: choose **Edit** in the menu of the basket, go to the **Permissions** tab of the **Manage basket** window, add the users or groups and choose their permissions.

### Publishing baskets

You can publish your baskets as web galleries with Phrasea Expose: the publication follows the basket, every asset added or removed is sent to it.

1. Open the basket: right-click on its title then **Edit**, or click the **Integrations** button of the basket view.
2. In the **Manage basket** window, go to the **Integrations** tab.
3. The first time, click **Authorize** under the Expose integration and sign in.
4. Click **New publication**, give it a title (and optionally a slug, a description, a parent publication or a profile), then click **Create & sync**.

The synchronization can then be run again (**Force sync**) or stopped (**Stop**, optionally deleting the publication from Expose). The layout, the downloads, the password, the terms and the other settings of the publication are set in Expose.

To continue, you must have access to Expose. See the [Expose user guide](../Expose/expose-documentation.md) for the whole procedure and the settings of the publications.

## Quarantine

When the analysis of an uploaded file rejects it (for example a possible duplicate, or an unexpected content), the asset is quarantined: it waits for a user to accept, merge or delete it. A quarantined asset shows the banner **This asset is quarantined**; **Analysis details** shows the analysis report and the possible duplicates.

**Quarantine**, at the bottom of the **Workspaces & collections** section, opens the list of the assets to review; the icon next to it shows them in the search instead.

1. Select an asset in the list (or click **Resolve** in its banner).
2. Read the analysis report and the **Possible duplicates**.
3. Choose an action:
    - **Bypass**: accept the asset as it is;
    - **Merge duplicates**: choose, for each attribute, the value to keep on the existing asset, then choose whether to **Keep the incoming file as a new version of the existing asset** or to **Discard the incoming file**;
    - **Move to trash** or **Delete permanently**.

The next asset is then shown; **Skip** goes to the next one without deciding. Check several assets to apply an action to all of them.

## Terms and conditions

A workspace can require the acceptance of its terms and conditions. When you open Databox, a window shows them: check **I have read and accept the Terms & Conditions** and click **Accept** to access the content of the workspace. **Not now** postpones the decision until the next reload of the page.

The terms are also requested when you export assets of the workspace, and on the share pages.

## Settings

The settings menu (gear icon) of the top bar holds:

- **Display profile**: see [Display profiles and pinned filters](#display-profiles-and-pinned-filters).
- **Theme**:
    - **Appearance**: **Light**, **Dark** or **System** (follows your device);
    - **Theme**: the colors and the style of the interface, among **Default** and the built-in themes (**Ocean**, **Forest**, **Sunset**…), or the theme of your organization under **Organisation**.
- **Language**: the language of the interface. **Data language…** chooses the language used for the attribute values and the translated names; **Default — use UI language** follows the language of the interface.
- **Cookies**, when your organization measures the audience with cookies: accept or refuse them.

Administrators find other entries in this menu (operation tasks, workflows, integrations, audit log, permissions of the instance): see [Administration](../../tech/Databox/administration.md).

## Reporting an issue

When your organization has enabled it, the **Report an issue** button (bottom left of the window) sends a report to the support team:

1. Choose the **Type**: **Bug**, **Improvement** or **Question**.
2. Fill in the **Summary** and the **Details**.
3. Optionally, add a **Screenshot**: click **Capture this page**, or paste an image.
4. Click **Create ticket**.

The page you are on and your session are attached to the report.

## Keyboard shortcuts

On macOS, use ⌘ where the table says Ctrl.

| Keys | Where | Action |
|---|---|---|
| Ctrl+A | Results, basket | Select all the loaded assets |
| Esc | Results, basket | Clear the selection |
| Ctrl+V | Search screen, outside a text field | Upload the pasted files |
| Enter | Search bar | Run the search, or apply the highlighted suggestion |
| ↑ / ↓ | Search bar | Browse the suggestions |
| Esc | Search bar | Close the suggestions |
| ← / → | Asset viewer | Previous / next asset |
| Esc | Asset viewer | Close the viewer |
| Space or K | Video | Play / pause |
| M / F / C | Video | Mute / full screen / subtitles |
| Tab / Shift+Tab | Batch editor | Next / previous attribute |
| Enter | Discussion | Send the message (Shift+Enter: new line) |
| Ctrl+B / Ctrl+I | Discussion | Bold / italic |
| Delete | Annotations | Delete the selected shape |
| Arrow keys | Annotations | Move the selected shape (Shift: faster) |
| Space (held) | Annotations | Move the image instead of drawing |
| Esc | Annotations | Cancel, leave the tool, then unselect |
| ← / → | Timeline of the region of interest or of an extract | Move by one frame (Shift: one second) |
| I / O | Timeline | Start / end of the range at the playhead |
| + / − / 0 | Timeline | Zoom in / zoom out / whole duration |
| Ctrl+U | Anywhere | **Switch user…** (users allowed to browse as another user) |
