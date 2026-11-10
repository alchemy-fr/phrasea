# Automation: share and export the links (XLSX)

The `share_export` action of the saved search automations adds the assets of a run to a
**share link**, then uploads an **XLSX file** listing them with the public URLs (permalinks)
of the chosen renditions, to an S3 bucket, an SFTP or an FTP server.

## Workspace

The action works on **one workspace**: the automation must have a workspace (`workspaceId`),
which limits the results of the saved search to this workspace. This filter is added to the
search at each run, whatever the conditions of the saved search (which may change over time):
they cannot widen it. For the other actions, the workspace is optional: when set, they only
get the results of this workspace.

An automation with a `share_export` action cannot be saved without a workspace, nor can its
workspace be removed while the action is there. The automation is deleted with its workspace.
An automation without workspace reaching a run anyway fails the action on each asset and
uploads nothing.

## Configuration

- **Destination**: `protocol` (`s3`, `sftp` or `ftp`) and its options, as for the
  `remote_export` action (same fields, secrets encrypted and never sent back).
- `pathTemplate`: the path of the file on the destination, default
  `{savedSearch}/{automation}-{datetime}.xlsx`. Variables: `{automation}`, `{savedSearch}`
  (slugified names), `{date}` (`Y-m-d` of the run), `{datetime}` (`Ymd-His`), `{runId}`.
  An existing file is replaced.
- `renditionIds`: the renditions to link, by ID of rendition definition of the workspace of
  the automation (at least one).
- `attributeIds`: optional attribute columns, by ID of attribute definition of the workspace
  of the automation (50 at most), and `locale` for the translated ones (the locales of the
  workspace otherwise). Several values are joined by line breaks.
- `uploadWhenEmpty`: upload a file (header only) when the run handled no asset; off by default.

An automation has at most one `share_export` action.

## Columns

ID, title, reference collection, creation date, URL of the share page
(`{DATABOX_CLIENT_URL}/s/{id}/{token}`), one column per rendition
(`{DATABOX_API_URL}/s/{share}/a/{asset}/r/{definition}?token=…`, a redirection to the file),
then one column per attribute. The headers are the current names of the definitions (the ID of
a deleted one, or of an attribute the owner may not view). Every cell is a text, never a formula.

The file lists the assets of the run: the new ones with the scope `new`, all the results
(up to the limit of assets per run) with the scope `all`.

## The share

The automation keeps **one share** for its workspace (a share only holds assets of a single
workspace), stored in `automation_share`, and fills it run after run: the permalinks stay the
same from one file to the next. Assets leaving the results are not removed from it. Another
workspace chosen for the automation gets a new share at the next run; the previous one stays.

- Created at the first run as "{saved search} Automation {automation}", owned by the owner of
  the saved search, without expiration: it can then be renamed, expired or disabled like any
  share. Deleted, it is created again at the next run (with new links).
- It only delivers the chosen renditions whose policy is public. A rendition which is not
  public, or deleted since, leaves its column empty, with a notice on the run; without any
  public one, no share is created and the assets are listed without links.
- An asset the owner may not read or share is skipped, as is an asset of another workspace.

## Failed upload

The upload happens at the end of the run: when it fails, the action fails (see the runs of
the automation), but the assets were already added to the share and marked as handled. With
the scope `new`, they are not listed again by the next runs: reset the cursor of the
automation to export them again.
