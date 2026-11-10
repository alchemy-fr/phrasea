# Automation: share and export the links (XLSX)

The `share_export` action of the saved search automations adds the assets of a run to a
**share link**, then uploads an **XLSX file** listing them with the public URLs (permalinks)
of the chosen renditions, to an S3 bucket, an SFTP or an FTP server.

## Configuration

- **Destination**: `protocol` (`s3`, `sftp` or `ftp`) and its options, as for the
  `remote_export` action (same fields, secrets encrypted and never sent back).
- `pathTemplate`: the path of the file on the destination, default
  `{savedSearch}/{automation}-{datetime}.xlsx`. Variables: `{automation}`, `{savedSearch}`
  (slugified names), `{date}` (`Y-m-d` of the run), `{datetime}` (`Ymd-His`), `{runId}`.
  An existing file is replaced.
- `renditions`: the renditions to link, by name (at least one).
- `attributes`: optional attribute columns, by definition slug (50 at most), and `locale`
  for the translated ones (the locales of the workspace otherwise). Several values are
  joined by line breaks.
- `uploadWhenEmpty`: upload a file (header only) when the run handled no asset; off by default.

An automation has at most one `share_export` action.

## Columns

ID, title, workspace, reference collection, creation date, URL of the share page
(`{DATABOX_CLIENT_URL}/s/{id}/{token}`), one column per rendition
(`{DATABOX_API_URL}/s/{share}/a/{asset}/r/{definition}?token=…`, a redirection to the file),
then one column per attribute. Every cell is a text, never a formula.

The file lists the assets of the run: the new ones with the scope `new`, all the results
(up to the limit of assets per run) with the scope `all`.

## The share

The automation keeps **one share per workspace** (a share only holds assets of a single
workspace), stored in `automation_share`, and fills it run after run: the permalinks stay the
same from one file to the next. Assets leaving the results are not removed from it.

- Created at the first run as "{saved search} Automation {automation}", owned by the owner of
  the saved search, without expiration: it can then be renamed, expired or disabled like any
  share. Deleted, it is created again at the next run (with new links).
- It only delivers the chosen renditions whose policy is public. A rendition which is not
  public leaves its column empty, with a notice on the run; a workspace without any public
  one gets no share, its assets are listed without links.
- An asset the owner may not read or share is skipped.

## Failed upload

The upload happens at the end of the run: when it fails, the action fails (see the runs of
the automation), but the assets were already added to the share and marked as handled. With
the scope `new`, they are not listed again by the next runs: reset the cursor of the
automation to export them again.
