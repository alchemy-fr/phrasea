---
title: Administration
---

# Uploader Administration

The admin is an EasyAdmin back office at `<UPLOADER_API_URL>/admin` (`App\Controller\Admin\DashboardController`). It signs the user in through Keycloak and needs `ROLE_ADMIN`.

The targets, their forms and their data are also edited in the Uploader client (Targets menu), and the upload links in its Upload links menu: see the [user documentation](../../user/Uploader/uploader-documentation.md).

## Home

The home page shows:

- counters: commits, uploaded assets and volume over time, unacknowledged commits, targets, upload links, and the failed messages of the queues;
- charts: uploads over 30 days, uploaded assets by file type, top targets over 30 days;
- the latest commits (acknowledged or pending) and the upload links used last.

## Menus

- **Permissions**: the global ACEs.
  - Target params permissions (`target_params`): edit the data of the targets.
  - Form schema permissions (`form_schema`): edit the forms of the targets.
  - Upload link permissions (`upload_link`): create upload links.
  - All permissions (advanced): every ACE.
- **Uploads**:
  - **Targets**: every target, the disabled and hidden ones included. The filters include the mode (Pull: no target URL; Push). The detail page shows the **Pull mode URL** (`Target::getPullModeUrl()`, `{UPLOADER_API_URL}/commits?target={id}`). The authorization key is never shown: an empty field keeps it.
  - **Upload Links**: every link, whoever created it.
  - **Commits**: the commits and their files. The **trigger again** action sends the notification of a commit to its target once more (`CommitNotifier::notifyAgain`), unless the commit is acknowledged.
  - **Assets**: the uploaded files, filtered by target, commit, upload link, acknowledgement or whether they are committed.
  - **Multipart Uploads**: the uploads in progress (storage bundle).
- **Notifications**: broadcasts, subscribers, in-app notifications, digests and preferences of the notifier.
- **Settings**: the stack configuration (Global Config), see [Configuration](./configuration.md#stack-configuration).
- **Dev** (`ROLE_TECH`): failed messages, queues, PHP info.

## Console commands

Run them in the API container as the `app` user (`su app -c "bin/console …"`).

| Command | Description |
|---|---|
| `app:create-target <slug> <name> <target-url> [--hidden]` | Creates a target, or updates the one with this slug (name, URL, hidden). The URL is mandatory; the key and the other settings are set in the admin. |
| `app:asset:clean-expired [-r <days>]` | Deletes the files older than `ASSET_DAYS_RETENTION` days (31 by default). Run every day by the scheduler. |
| `alchemy:storage:prune-multipart-uploads` | Aborts the abandoned multipart uploads. Run every day by the scheduler. |

## Report log

The Uploader sends one action to the report service: `upload_commit`, when a commit is stored, with the ID of the commit and:

- `userId` and `username` of the sender (`upload-link:<name or id>` for a commit sent through an upload link);
- `totalSize`, in bytes;
- `fileCount`.

A retried commit (see [API reference](./api.md#commits)) is not logged again.
