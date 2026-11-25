---
title: Uploader Service Documentation
status: Pending review
---

# Uploader Client and Administrator

## Glossary

- **Target**: Destination of the files: a Databox workspace, a Phraseanet collection or a third-party application, with its form and the groups allowed to use it.  
- **Targets**: Menu of the Uploader client that edits the settings of each target, in three tabs:  
  - **Properties**: Name, URL, authorization, destination and allowed groups of the target (administrators).  
  - **Form**: Builds the indexing form filled in by the user (form builder).  
  - **Data**: Additional indexing data of the target, not accessible to the user.  
- **Asset**: Uploaded file.  
- **Commit**: Grouping of assets and information entered in the target form (in JSON format).  
- **Upload link**: Secret URL letting someone without an account send files to one target, within limits.  
- **Multipart upload**: Files are split into chunks and reassembled on the server once the upload is complete.

## Basic Concepts

- The Phrasea Uploader service consists of an API, a user interface called “Uploader client”, a “worker” and a “scheduler” (daily cleanup). These components rely on an S3 storage, a database, cache servers and a message bus. The API and the worker can be scaled as needed.  
- The Uploader contains Targets.  
- Each Target is associated with a form, made up of various widgets.  
  - The form allows the user uploading files to enter information related to the batch of files sent.  
  - The form definition is done in an online editor.  
- File uploads via a Target are subject to group rights.  
- A destination can be defined for each Target.  
  - A Target can be:  
    - A Phrasea collection  
    - A Phraseanet collection  
    - A third-party application  
- The Uploader is not a storage: once the destination has received the files, they are deleted from the Uploader.

- By default, the account database is that of the “Phrasea” realm in Keycloak.  
  - The Uploader service uses an OAuth client in Keycloak for authentication.

## File Upload Workflow with the Uploader Client Interface

This section describes the main steps and actions triggered when adding files with the Uploader client to Databox.

### Interaction between Uploader and Phrasea Databox

The sequence of the requests is described in the [technical documentation](../../tech/Uploader/request_flow.md).

- A user authenticates.  
- Targets are presented to them, according to their group membership.  
  - The user selects a Target.  
  - The user selects files via:
    - File selector
    - Drag and drop

    or imports a file from its URL.

  - The user proceeds to the next screen to complete the form.

- File upload starts in the background.  
  - The user completes and validates the form.  
    - Once all files are received by the Uploader service, the destination defined in the Target is notified that a batch of files is available:  
      - Phrasea Databox  
      - Phraseanet  
      - Third-party application able to declare an endpoint listening for emitted notifications

    - The Databox worker starts creating an asset for each file in the batch and downloads the file from the Uploader storage configured in the stack.  
    - The information from the form is used to populate the attributes of the batch of files sent.

> *Supported file systems by the Uploader service and minimum requirements*

The Uploader is compatible with S3 storage. For optimal operation, the file system must support certain features (see the corresponding documentation).  
Tested and validated systems are AWS S3 and Minio (see the stack documentation, pay attention to containers used as primary datastore).  
For optimal use, the Uploader supports:  
- Multipart upload, allowing large files to be transmitted  
- Technical capabilities, such as maximum accepted size, depend on the file system used

## Uploading Files

### Choosing a target

The home page lists the targets the user may use: “Where do you want to upload?”. When there is only one, it opens directly. A user without any target sees a message saying so.

### Steps

The upload page guides the user through numbered steps:

1. **Files**: drag files onto the page, or click to select them (“Add files”). The limits are shown under the drop zone: size per file, number of files and total size per upload. The accepted file types are listed behind the information icon. A file of a type that is not accepted, or too large, is refused with a message; a file already selected is left out. When the stack allows only one file per upload, the page switches to a single file mode: a new file replaces the previous one.
2. **Information**: the form of the target, when it has one. The files are already being sent while the user fills it in. “Confirm and send” validates it.
3. **Finalization** (or **Upload** without form): the upload ends once every file has arrived. “Edit the information” goes back to the form.
4. **Done**: the number of files sent and the target. “Send more files” starts a new upload to the same target, “Choose another destination” goes back to the list.

“Cancel” stops the upload: the files already sent and the information entered are lost. Leaving the page during an upload asks for a confirmation.

### Failures and resuming

- A file that fails is retried automatically. When it still fails, it is shown with its error, without blocking the others: “Retry” sends it again, “Remove” leaves it out.  
- When the connection is lost, the upload waits for it and resumes on its own.  
- After a reload of the page, dropping the same file again resumes its upload where it stopped.  
- When the upload cannot be finalized, an error is shown: “Retry”, or “Edit the form” to fix the information.

### Being notified

When the form of the target offers “Notify me when done”, the user is notified by email once the destination has processed the files.

## Importing a File from a URL

Under the drop zone, “import a file from a URL” opens the “Import from a URL” page:

- enter the URL of the file and fill in the form of the target, then “Import”;
- the Uploader fetches the file itself, then sends it to the target like any other upload: the page can be left right away;
- “Import another file” starts again.

The file must be public: an address of the internal network is refused. It is fetched in 10 minutes at most and is subject to the same limits as the other files (size, accepted types). The number of imports is limited per hour and per user. This page is not available through an upload link.

## Upload Links

An upload link lets someone without an account (a photographer, a partner…) send files to one target. What is sent through it is attributed to the user who created it.

### Managing the links

The users allowed to create links see the “Upload links” menu. It lists the links of the user (every link, for an administrator) with their target, status, files and size sent, expiration and last upload.

- **New link**: choose the target, a name (who the link is for), an expiration date (a week by default, within the maximum of the stack), and optionally a maximum number of files and a maximum total size, over every upload made with the link. “Create the link” shows the link to copy.  
- **Copy the link**: copies its URL again.  
- **Edit**, **Disable** / **Enable**, **Delete**: a deleted link stops working for good; what was sent with it stays.  
- **New URL**: replaces the URL of the link. The former URL stops working at once.

The status of a link is “Active”, “Disabled”, “Expired” or “Limit reached”. A link also stops working when its creator can no longer create links or upload to its target.

### Uploading through a link

The person who receives the link opens it in a browser, without signing in. The page shows the target and what is left on the link (files, size), then the same steps as a signed-in upload. “Notify me when done” is not offered: nobody is behind the link to be notified.

On the next upload with the same link, from the same browser, “Reuse my previous information” fills the form with what was entered last time; “Forget my previous information” erases it. This information stays in the browser: the other holders of the link never see it.

When the link is invalid, disabled, expired or no longer valid, the page says so and asks to request a new link from the person who sent it.

## Menus and Settings

- **Settings** menu: theme (light, dark or system, and the theme of the organisation), language (English, French, German, Spanish), and the cookie consent when Matomo asks for it.  
- **All services**: back to the dashboard of the Phrasea services, when the stack displays it.  
- **User** menu: my account (Keycloak), sign out, “Manage groups…” for the users allowed to manage groups, and “Debug user” on a development stack.

## Configuration

### Uploader and Phrasea Databox

Configuring a Target for Phrasea

#### Databox Configuration

In the Databox client application, on a workspace, define a new “Uploader” integration:

- The integration is specific to the workspace and must be defined for each one.  
- Its configuration:

```yaml
# Base URL of the Uploader API
baseUrl:
# Target collection ID (optional)
collectionId:         ~
# Security key to authenticate Uploader requests
securityKey:    
```

The security key is generated when left empty. The integration then shows the values to enter in the Uploader: the “Target URL”, the “Authorization Scheme” (`ApiKey`) and the “Security Key”.

#### Uploader Admin Configuration

Configuration on the Uploader side is done by an administrator:

- Add a Target: “Targets” menu > “New target” in the Uploader client, or Uploads > Targets in the admin.  
- Parameters to enter, obtained from the “Uploader” Databox integration:  
  - “Target URL”: the endpoint on the “Phrasea” side where file additions will be notified  
  - Authorization key: the security key of the integration  
  - Authorization Scheme: `ApiKey`, the default  
- Then, in the “Form” tab of the target, choose the Databox workspace to offer its attributes in the form.

### Uploader and Phraseanet

Target configuration.  
In Push mode, the operation of the Uploader with Phraseanet is identical in mechanics; only a few parameters change:

- “Target URL”: the enqueue endpoint of Phraseanet, `https://<phraseanet>/api/v1/upload/enqueue/`  
- Authorization Scheme: `OAuth`  
- Authorization key: a Phraseanet API token  
- Default destination: the ID of the Phraseanet collection

In Pull mode, the target has no URL: Phraseanet fetches the uploads itself, from the “Pull mode URL” shown by the target in the admin. See the [technical documentation](../../tech/Uploader/02_setup.md#target-for-phraseanet).

## Target Form Configuration

### Targets

In the Uploader client interface: “Targets” menu. The list shows, for each target, whether it has a form and data. Click on a Target to edit it: its page has a “Properties” tab, a “Form” tab and a “Data” tab, each with its own “Save” button. Switching tabs keeps unsaved changes. The breadcrumb above the title leads back to the list.

Each tab requires its own permission: the Properties one is for the administrators, the Form one requires the form edition permission and the Data one the target data edition permission. A user who has only one of them sees only that tab.

Disabled and hidden targets are managed in the admin only.

### New target

An administrator creates a target from the list, with the “New target” button. The dialog asks for the same properties as the Properties tab; only the name is required. “Create the target” saves it and opens its page, where its form and data are then added.

### Properties tab

The “Properties” tab sets the properties of the target:

- **Name**: required.  
- **Slug**: optional and unique; lowercase letters, digits, “-” and “_”, starting with a letter (100 characters at most).  
- **Description**.  
- **Target URL**: where the uploads are pushed, in https. Empty for the pull mode: the destination fetches the uploads.  
- **Authorization scheme** and **Authorization key**: push mode only. The scheme defaults to “ApiKey” (“OAuth” for Phraseanet).  
- **Default destination**: the ID of a Databox collection, or of a Phraseanet collection.  
- **Allowed groups**: empty to allow every user.

The authorization key is never shown: leaving the field empty keeps it, and the “Remove the key” checkbox removes it. It is sent to the target URL: once this URL leads to another host (scheme, host or port), the key is removed on save, unless a new key is entered along with it.

### Form tab

Documentation and example [form](../../tech/Uploader/form_config.md)

Each target has one form, for every language: its labels, help texts and placeholders are translated in the form itself. The Form tab builds the form by dragging the attributes of a Databox workspace (entity lists included), Databox values (name, story, “Notify me when done”), custom fields and sections, and shows its rendering. "Delete the form" removes it: the target then accepts no indexing data from the user.

Forms described in JSON (LiForm) before version 5 were converted to the form builder on upgrade; see the [technical documentation](../../tech/Uploader/form_config.md#migration-from-liform) for what the conversion keeps.

### Data tab

The “Data” tab of the target editor allows you to pass, as a JSON object, additional data added to every upload of the target to describe the assets. Emptying it removes the data of the target.

Data defined in the “Data” tab takes precedence over information entered in the form. Therefore, it is unnecessary to declare attribute values in the form if they are already referenced in the target data.

## Notifications

The sender can be notified via the Uploader: when the form of the target has the “Notify me when done” field and the sender checks it, the Uploader notifies them once the destination has acknowledged the files. Like other modules, the “Uploader” module sends these notifications through the notifier bundle: by email, the in-app notifications being recorded without any interface displaying them yet.

## FAQ

**Why an external service?**

- This isolates file addition in a dedicated interface  
  - Without giving access to the entire DAM  
  - An interface reduced to the essentials for users  
  - Can work in “Pull” mode: the consuming application queries the Uploader from a secure area at its chosen frequency (Databox has no “Pull” mode: its Uploader integration only receives the uploads pushed by the Uploader)

**Why a form and target data?**

- The form is filled in by the user uploading the assets  
- The target data allows you to add values to the batch of assets without going through the form; these values are not accessible to the user uploading the assets
