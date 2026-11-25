---
title: 'Notifications'
---

# Notifications

Databox notifies users by email and in the application when something happens on what they
follow: the assets and collections they subscribed to, the discussions they take part in or
are mentioned in, the values waiting for their approval. Notifications are handled by the
shared `lib/php/notifier-bundle` (its `README.md` describes the bundle itself); this page
describes how Databox uses it.

## Concepts

- **Topic**: a kind of notification (`asset:update`, `discussion:new_comment`…), declared in
  `databox/api/config/packages/alchemy_notifier.yaml` with its channels and its digest.
- **Subscription**: a user follows an event, globally or on one object (an asset, a
  collection, a discussion thread).
- **Channels**: `email` and `in_app` for every Databox topic.
- **Digest**: the notifications of a topic are grouped into one email after 10 minutes of
  inactivity, or 1 hour at most.
- **Preferences**: a user can opt out of a topic on a channel; everything is on by default.

The author of a change is never notified of it.

## Databox topics

| Topic                       | Sent when                                                             | Digest |
|-----------------------------|-----------------------------------------------------------------------|--------|
| `asset:update`              | the attributes of a followed asset change, or of an asset of a followed collection | no |
| `collection:asset_add`      | an asset is added to a followed collection                           | per collection |
| `collection:asset_remove`   | an asset is removed from a followed collection                       | per collection |
| `discussion:new_comment`    | a comment is posted on a followed asset, an asset of a followed collection, or a thread the user takes part in or is mentioned in | yes |
| `entity_list:pending_value` | a value awaits approval in an [entity list](./entity-lists.md); sent to the list owner | no |
| `user:exception`            | a background task of the user failed (an integration, a synchronization…) | no |
| `automation:report`         | an automation sends its report (see [automations](./automations.md)) | no |

`asset:delete` and `asset:new_comment` are declared too, but comments are sent under
`discussion:new_comment`.

Admins can also send a message to every user (`admin:message`, below).

## Following

Assets and collections are followable (`App\Entity\FollowableInterface`):

| Object     | Events                                                        |
|------------|---------------------------------------------------------------|
| asset      | `asset:update`, `asset:delete`, `asset:new_comment`           |
| collection | `asset:add`, `asset:remove`, `asset:update`, `asset:new_comment` |

- `POST /assets/{id}/follow`, `POST /assets/{id}/unfollow`, and the same on
  `/collections/{id}`: `READ` on the object; the body `{key}` names one event, no key means
  all of them. The output `topicSubscriptions` lists the followed events.
- The owner of an asset or a collection is subscribed to its comments automatically, unless
  disabled in the administration (`notificationSettings`).

:::warning
The client sends the topics to follow as `topics`, which the API ignores: its per-topic
checkboxes follow or unfollow every event of the object.
:::

## Mentions

In a discussion, `@[name](userId)` mentions a user (`App\Service\Discussion\MentionExtractor`).
The author and the mentioned users are subscribed to the thread, and get its new comments.
The notification links to `/assets/{id}#discussion-{messageId}`.

## Quiet operations

Requests carrying `X-Notification-Disabled` (or `X-Webhook-Disabled`) do not notify, nor
the workers they trigger (`App\Consumer\Middleware\QuietContextMiddleware`). The upload dialog
offers it: "Quiet (no notification, no webhook)".

## API

| Method and path                         | Effect                                      |
|-----------------------------------------|---------------------------------------------|
| `GET /notifications`                    | In-app notifications (`page`, `limit` up to 100, `unread`). |
| `GET /notifications/unread-count`       | The unread count.                           |
| `POST /notifications/{id}/read`, `/unread` | Mark one notification.                   |
| `POST /notifications/read-all`          | Mark all as read; drops the pending digests. |
| `DELETE /notifications/{id}`            | Delete one.                                 |
| `GET`, `PUT`, `PATCH /notification-preferences` | The preferences of the user.        |

In-app notifications are also pushed in real time on the private channel
`private-user-{userId}`, event `notification` (see [realtime](./realtime.md)).

## Client

- The bell of the top bar (`features/notifications/NotificationsMenu.tsx`): "Notifications",
  "Mark all as read", "No notification", and a toast "New notification" with "Open". The
  unread count is refreshed every minute and on each pushed notification.
- Links of notifications go through `/notification-uri`, which opens the target.
- "Follow" / "Unfollow" on the asset viewer ("Updates", "Deletion", "New comments"), and the
  **Notifications** tab of the collection management ("Asset added", "Asset removed", "Asset
  updated", "New comment").

The preferences have no screen in the Databox client.

## Commands

| Command | Effect |
|---------|--------|
| `alchemy:notifier:broadcast <topic> [payload]` | Send a topic to a whole audience, every user of the identity provider by default (`--channel`, `--audience`, `--exclude-user`). |
| `alchemy:notifier:digest:flush [--force]`      | Send the digests whose inactivity window has elapsed; scheduled hourly. |

See also [console commands](./console-commands.md).

## Configuration

| Variable                | Default | Meaning                                       |
|-------------------------|---------|-----------------------------------------------|
| `NOTIFICATIONS_ENABLED` | `1`     | Turns every notification off when false.      |
| `DATABOX_API_URL`       |         | Public URL of the API, used in the emails.    |
| `DATABOX_CLIENT_URL`    |         | URL of the client, target of the links.       |

The email templates are in `databox/api/templates/notifications/`.

## Administration

The EasyAdmin pages of the bundle list the notifications, preferences, digests and
subscribers, and send broadcasts (topic `admin:message`).
