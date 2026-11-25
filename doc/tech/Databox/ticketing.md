---
title: 'Ticketing'
---

# Ticketing

A floating **Report an issue** button lets the signed-in users of the Databox client open a
Jira issue without leaving the page. The ticket carries the report, the page the user was
on, their session and, optionally, a screenshot. The Jira credentials stay on the server
side of the Next.js client: the browser never sees them.

## Concepts

- **Kind**: `bug`, `improvement` or `question`. A bug is created with the bug issue type,
  the other kinds with the default issue type.
- **Page context**: collected in the browser when the button is clicked
  (`databox/client/src/features/ticketing/context.ts`): URL, path, page title, referrer, UI
  language, data language, theme, timezone, viewport and screen sizes, user agent, capture
  time, and the last 10 uncaught errors and unhandled rejections of the session (stacks cut
  at 2,000 characters).
- **Session context**: never sent by the browser. The server rebuilds it from the access
  token after validating it against Keycloak, so a ticket cannot be opened on behalf of
  somebody else.

## Behavior

The Databox API is not involved: the ticket goes to the route handler of the client,
`POST /api/ticketing` (`databox/client/src/app/api/ticketing/route.ts`; the paths below are
relative to `databox/client/src/`), which:

1. answers 404 `Ticketing is disabled` when `DATABOX_TICKETING_ENABLED` is off;
2. reads the Jira settings (`lib/ticketing/config.ts`) and answers 503 `Ticketing is not
   configured` when `DATABOX_TICKETING_JIRA_URL`, `DATABOX_TICKETING_JIRA_API_TOKEN` or
   `DATABOX_TICKETING_JIRA_PROJECT_KEY` is empty (logged as `[ticketing] misconfigured`);
3. requires a bearer token (401 `Authentication required` otherwise) and validates it with
   the Keycloak `userinfo` endpoint of the realm (`KEYCLOAK_INTERNAL_URL`, else
   `KEYCLOAK_URL`; realm `KEYCLOAK_REALM_NAME`, `phrasea` by default). A refused token
   answers 401, any other failure 502 `Unable to verify the session`
   (`lib/ticketing/session.ts`);
4. validates the payload (`lib/ticketing/payload.ts`, 400 with the reason): a known kind, a
   summary (200 characters at most) and a description (10,000 at most), a page URL; a
   screenshot must be a PNG, JPEG or WebP `data:` URL of 5 MB at most; the other strings are
   capped;
5. applies a rate limit of 5 tickets per user per 10 minutes (`lib/ticketing/rateLimit.ts`,
   in memory, per client process): 429 with `Retry-After` beyond;
6. creates the issue (`lib/ticketing/jira.ts`), then attaches the screenshot. A Jira refusal
   answers 502 `Jira rejected the ticket:` followed by Jira's messages. A failed screenshot
   upload does not fail the request: the answer reports `screenshotAttached: false`.

The answer is `key`, `url` (`{DATABOX_TICKETING_JIRA_URL}/browse/{key}`) and
`screenshotAttached`.

### What is sent to Jira

`POST {DATABOX_TICKETING_JIRA_URL}/rest/api/{version}/issue` with the fields:

| Field         | Value                                                                                       |
| ------------- | ------------------------------------------------------------------------------------------- |
| `project`     | `DATABOX_TICKETING_JIRA_PROJECT_KEY`                                                        |
| `issuetype`   | `DATABOX_TICKETING_JIRA_BUG_ISSUE_TYPE` for a bug, `DATABOX_TICKETING_JIRA_ISSUE_TYPE` otherwise |
| `summary`     | the summary                                                                                 |
| `labels`      | `DATABOX_TICKETING_JIRA_LABELS` plus `databox-{kind}` (e.g. `databox-bug`)                  |
| `description` | the details, then a **Page** section (link and page context), a **Session** section and, if any, a **Console errors** section |

The **Session** section lists the user's name, username, email, user ID, session ID (`sid`,
the Keycloak session), roles, groups, realm, client ID (`azp`) and token expiration
(`lib/ticketing/description.ts`). With API version `3` (Jira Cloud) the description is in
the Atlassian Document Format, with version `2` (Jira Server / Data Center) in wiki markup.

The screenshot is uploaded with `POST …/issue/{key}/attachments` (header
`X-Atlassian-Token: no-check`) as `screenshot-{timestamp}.png` (or `.jpg`, `.webp`).

Jira authentication: with `DATABOX_TICKETING_JIRA_USER` set, Basic with the user and the API
token (Jira Cloud); without it, the token is sent as a Bearer personal access token (Jira
Server / Data Center).

## Client screens

- The button: a round life buoy button at the bottom left of every page, with the tooltip
  **Report an issue** (`features/ticketing/TicketButton.tsx`). It is rendered only when
  ticketing is enabled and a user is signed in, stays clickable above the open dialogs, and
  is hidden while its own dialog is open.
- The dialog **Report an issue** (`features/ticketing/TicketDialog.tsx`): **Your report is
  sent to our issue tracker along with the page you are on and your session.**
  - **Type**: **Bug** (default), **Improvement**, **Question**;
  - **Summary** (placeholder **Short title of the problem**);
  - **Details**, with the help **What did you do, what did you expect, what happened?**;
  - **Screenshot**: **Capture this page** when the browser supports screen capture (the tab
    is captured with `getDisplayMedia`, the dialog hidden meanwhile; PNG, or JPEG when the
    PNG exceeds 5 MB), or an image pasted with Ctrl+V (⌘V);
  - **Data attached to the ticket** (collapsed): **Page**, **User**, **Browser** and the
    number of **Console errors**;
  - **Cancel** and **Create ticket**. Closing with unsaved fields asks for confirmation.
- On success, a toast reads **Ticket** followed by the key and **created**, with an **Open**
  action to the Jira issue; **The ticket was created but the screenshot could not be
  attached** when the upload failed. An error shows the message of the route handler.

While an admin [impersonates](./impersonation.md) another user, the request is sent with the
admin's own token and no impersonation header: the reporter in Jira is the signed-in admin.
The **User** line of the dialog shows the user being browsed as.

## Permissions

Any signed-in user may report an issue: no role is required. Anonymous visitors (public
pages, shares) do not get the button, and the route handler refuses requests without a
valid token.

## Configuration

The variables are read by the `databox-client` container (category `databox` of `.env`,
passed in `docker-compose.yml`). Only `DATABOX_TICKETING_ENABLED` reaches the browser, as
`ticketing.enabled` of the runtime configuration (`lib/config/server.ts`).

| Variable                                | Default (`.env`) | Description                                                       |
| --------------------------------------- | ---------------- | ----------------------------------------------------------------- |
| `DATABOX_TICKETING_ENABLED`             | `false`          | shows the button and opens the route handler                     |
| `DATABOX_TICKETING_JIRA_URL`            | empty            | Jira base URL, e.g. `https://acme.atlassian.net` (required)       |
| `DATABOX_TICKETING_JIRA_USER`           | empty            | Jira Cloud account email; empty for a personal access token       |
| `DATABOX_TICKETING_JIRA_API_TOKEN`      | empty            | API token (Cloud) or personal access token (Server / DC) (required) |
| `DATABOX_TICKETING_JIRA_PROJECT_KEY`    | empty            | target project (required)                                         |
| `DATABOX_TICKETING_JIRA_ISSUE_TYPE`     | `Task`           | issue type of the improvements and questions (`Task` when empty)  |
| `DATABOX_TICKETING_JIRA_BUG_ISSUE_TYPE` | `Bug`            | issue type of the bugs (the default issue type when empty)        |
| `DATABOX_TICKETING_JIRA_LABELS`         | `databox`        | comma-separated labels added to every issue                       |
| `DATABOX_TICKETING_JIRA_API_VERSION`    | `3`              | `3` for Jira Cloud, `2` for Jira Server / Data Center             |

The route handler also reads `KEYCLOAK_INTERNAL_URL` / `KEYCLOAK_URL`, `KEYCLOAK_REALM_NAME`
and `CLIENT_ID`, already set for the client. See [Configuration](./configuration.md) and
[Environment variables](../Configuration/01_env_var.mdx).

## See also

- [Impersonation](./impersonation.md)
- [Configuration](./configuration.md)
