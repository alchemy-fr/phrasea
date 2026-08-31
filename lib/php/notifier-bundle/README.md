# Alchemy Notifier Bundle

A Symfony bundle to notify users across multiple channels (Email, SMS, In-App via
Pusher), with per-user preferences and object subscriptions.

Users are identified by their **Keycloak `userId` (UUID)**, which is the uniqueness
key throughout the bundle. The application owns the Twig templates (one per topic /
channel); the bundle provides the Doctrine entities, the delivery pipeline and a REST
API.

## Concepts

| Concept | Description |
|---|---|
| **Subscriber** | A notifiable user, keyed by `userId`. Contact info (email, phone, locale, name) is resolved from Keycloak on first use. |
| **Subscription** | A subscriber's interest in an `event`, optionally scoped to an object `(objectType, objectId)`. Without an object, it is global to the event. |
| **Topic** | A notification kind (e.g. `asset:comment`), declared in config, delivered through one or more channels. |
| **Channel** | `email`, `sms` or `in_app`. |
| **Preference** | A per-subscriber opt-out for a `(topic, channel)` pair. Enabled by default. |
| **Notification** | A persisted in-app notification (history + unread badge). |
| **Broadcast** | The trace of one broadcast: what was sent, to which audience, by which user, and how it went. |

## Installation

Register the bundle:

```php
// config/bundles.php
return [
    // ...
    Alchemy\NotifierBundle\AlchemyNotifierBundle::class => ['all' => true],
];
```

The bundle registers its Doctrine mappings automatically (attribute mapping,
alias `notifier`). Generate a migration to create its tables:

```
bin/console doctrine:migrations:diff
```

Import the REST routes (optional):

```yaml
# config/routes/alchemy_notifier.yaml
alchemy_notifier:
    resource: '@AlchemyNotifierBundle/config/routing.yaml'
```

The in-app channel relies on `alchemy/core-bundle`'s Pusher integration, so make sure
Pusher is configured (`alchemy_core.pusher`).

## Configuration

```yaml
# config/packages/alchemy_notifier.yaml
alchemy_notifier:
    enabled: '%env(bool:NOTIFICATIONS_ENABLED)%'
    # Twig namespace under which templates live
    template_namespace: '@notifications'
    # Base URL of the front client, used by the `notification_url()` Twig function
    client_url: '%env(DATABOX_CLIENT_URL)%'
    # Client route intercepting notification links (see "Click-through links")
    notification_uri_path: '/notification-uri'
    # Channels used by topics that don't declare their own
    default_channels: [email, in_app]
    # Pusher channel/event for in-app notifications
    in_app_channel_prefix: 'private-user-'
    in_app_event: 'notification'
    # Default audience of a broadcast: `keycloak` or `subscribers`
    user_directory: keycloak
    # Page size used when listing the users of the identity provider
    directory_batch_size: 100
    topics:
        # Keys containing a colon must be quoted (YAML reserves `:`)
        'asset:comment':
            channels: [email, in_app]
            user_configurable: true
        'account:security':
            channels: [email, sms, in_app]
            # not shown in the preferences UI (cannot be opted out)
            user_configurable: false
```

### Topic naming convention

Topic keys follow **`object:action`** (e.g. `asset:update`, `collection:asset_add`,
`discussion:new_comment`). The segment before the colon is the object/domain, the
segment after is the action. Alchemy apps use the colon form; it keeps topic keys
aligned with the subscription **event** keys shared with the frontend
(`asset:update`, `asset:add`, …).

Because `:` is a reserved indicator in YAML, **topic keys must be quoted** in
`alchemy_notifier.topics` (`'asset:update':`), otherwise the file fails to parse.
The key is turned into the template path by replacing `.` and `:` with `/`, so
`asset:update` resolves to `asset/update/`.

Register the template namespace in Twig:

```yaml
# config/packages/twig.yaml
twig:
    paths:
        '%kernel.project_dir%/templates/notifications': notifications
```

## Templates

For each `(topic, channel)`, the bundle loads
`{namespace}/{topic-with-slashes}/{channel}.{ext}`. Dots and colons in the topic key
become slashes. A template may define a `subject` block and a `body` block.

```
templates/notifications/asset/comment/email.html.twig
templates/notifications/asset/comment/in_app.html.twig
templates/notifications/asset/comment/sms.txt.twig   (channel sms -> .txt.twig)
```

```twig
{# templates/notifications/asset/comment/email.html.twig #}
{% block subject %}New comment on {{ assetTitle }}{% endblock %}
{% block body %}
    <p>Hi {{ recipient.displayName }},</p>
    <p>{{ author }} commented: {{ comment }}</p>
{% endblock %}
```

Every template receives the `params` you pass plus a `recipient` variable
(`userId`, `displayName`, `email`, `locale`). If a template does not exist for a
channel, that channel is skipped.

### Click-through links

A notification points at a client screen through a **client URI** — a path the
front-end knows how to resolve, e.g. `/assets/42` or `/assets/42#discussion-7`.
In-app templates expose it through the `uri` block; e-mail templates need an
absolute link instead.

Rather than teaching the backend how to build every client route, wrap the URI
with the `notification_url()` Twig function: it produces a link to a single
generic client entry point, which resolves the final destination itself.

```twig
{# /assets/42  ->  https://databox.example.com/notification-uri?uri=%2Fassets%2F42 #}
<a href="{{ notification_url(url)|e('html_attr') }}">…</a>
```

The function returns the URI unchanged when it is already absolute (`http(s)://`)
or when `client_url` is not configured, and `null` for an empty URI. The client
route it points at is `{client_url}{notification_uri_path}?uri={uri}`; the front-end
must intercept `notification_uri_path` and redirect to the target the URI describes.

### Translations

Templates are rendered under the recipient's locale, so keep the literal strings
in translation catalogs rather than hard-coded in the template. Use the `trans`
filter with the `notifications` domain and store the messages in
`translations/notifications.<locale>.yaml`:

```twig
{# templates/notifications/asset/comment/email.html.twig #}
{% block subject %}{{ 'asset.comment.email.subject'|trans({'%name%': name}, 'notifications')|raw }}{% endblock %}
{% block body %}{{ 'asset.comment.email.body'|trans({
    '%recipient%': (recipient.displayName|default(recipient.email))|e,
    '%author%': author|e,
    '%name%': name|e,
    '%url%': notification_url(url)|e('html_attr'),
}, 'notifications')|raw }}{% endblock %}
```

```yaml
# translations/notifications.en.yaml
asset:
    comment:
        email:
            subject: 'New comment on %name%'
            body: '<p>Hi %recipient%,</p><p><strong>%author%</strong> commented on <a href="%url%">%name%</a>.</p>'
```

Because HTML bodies use `|raw` on the translated string, **escape every dynamic
value** yourself (`|e` for text, `|e('html_attr')` for URLs) to avoid XSS — the
catalog message is trusted, the interpolated values are not.

The locale is resolved per recipient: `email` / `sms` render under the
subscriber's `locale` at send time, and the in-app history renders under the
recipient subscriber's `locale` at read time. When no locale is available the
translator's default locale (and its fallbacks) is used. Switching the active
locale requires `symfony/translation`; without it templates still render in the
default language.

## Usage

```php
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use Alchemy\NotifierBundle\Model\NotifyOptions;
use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;

// Notify a single user directly
$notifier->notifyUser($userId, 'asset:comment', [
    'assetTitle' => $asset->getTitle(),
    'comment' => $message,
]);
```

Recipients are described by **selectors** (`NotifySelectorDto`). A selector targets
users explicitly (`userIds`), and/or everyone subscribed to an `event` — optionally
scoped to an object (`objectType` + `objectId`). Each selector carries the `TopicDto`
(topic + template params) to send:

```php
// Subscribe a user to an event on a given object
$subscriptions->subscribe($userId, new NotifySelectorDto(
    event: 'asset:comment',
    objectType: 'asset',
    objectId: $asset->getId(),
));

// Notify every subscriber of that event on that object
$notifier->notify(
    [
        new NotifySelectorDto(
            event: 'asset:comment',
            objectType: 'asset',
            objectId: $asset->getId(),
            topic: new TopicDto('asset:comment', $params),
        ),
    ],
    new NotifyOptions(excludeUserId: $authorId), // don't notify the author
);
```

A selector must provide at least an `event` or some `userIds`. An `event` subscription
without `objectType`/`objectId` is global to that event; object-scoped notifications are
matched exactly (an object-scoped selector does not reach global subscribers).

Sending is dispatched asynchronously through Messenger
(`Alchemy\NotifierBundle\Message\SendNotification`); route it to a transport as usual.

## Broadcasting

`broadcast()` sends a topic to a whole **audience**, resolved in the worker by a
**user directory**:

| Directory | Reaches |
|---|---|
| `keycloak` *(default)* | Every enabled user of the realm, paginated |
| `subscribers` | Only the users already known locally (i.e. notified at least once) |

```php
use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Model\BroadcastOptions;

$notifier->broadcast('service:announcement', $params, new BroadcastOptions(
    channels: [ChannelType::InApp],   // subset of the topic channels (default: all of them)
    excludeUserId: $currentUserId,
    directory: 'keycloak',
));
```

Recipients are never materialized in the message: the directory is streamed at
delivery time, contact info comes from the listing itself (no lookup per user),
and one unreachable recipient does not abort the run. **Route
`Alchemy\NotifierBundle\Message\BroadcastNotification` to a transport** — fanning
out over a whole realm has no business running inside a web request.

Every broadcast is recorded in **`notifier_broadcast`** (entity `Broadcast`):
topic, payload, channels, audience, the **userId of whoever sent it**
(`initiatorUserId`), the excluded userId, the delivered/failed counts and the
start/completion timestamps. The row is written *before* dispatching, and
`BroadcastNotification` carries nothing but its id — the worker reads everything
back from that row, which stays the single source of truth for what was sent. A
run the worker never picked up therefore stays visible, with `completedAt` still
null. The initiator defaults to the authenticated user; pass `initiatorUserId`
explicitly when broadcasting outside a request.

Register another audience by implementing `UserDirectoryInterface` (a Keycloak
group, a tenant, …): it is auto-tagged, and its `getName()` becomes usable as a
`directory`. Change the default with `alchemy_notifier.user_directory`.

From the CLI:

```
bin/console alchemy:notifier:broadcast <topic> '<json-payload>' [--channel=email] [--audience=subscribers] [--exclude-user=<userId>]
```

## Announcements from the admin

The bundle ships a built-in topic, **`admin:message`**, with its own templates.
Administrators compose a free-form announcement through the **Create** action of
the `Broadcast` CRUD (`BroadcastCrudController`), which is also the history
screen: sending one is just creating a `Broadcast` row. The controller does not
persist it itself — it hands it to `NotifierManager::dispatchBroadcast()`, so
the row and the dispatched message always agree. Existing rows are read-only.

Nothing has to be declared for it to work: `admin:message` is registered
automatically, and its templates are resolved from the bundle when the
application does not define its own (any template the application declares under
its own namespace wins).

The announcement is sent as written — it is **not** translated per recipient,
unlike the catalog-based templates of the application's own topics.

```php
$notifier->broadcast('admin:message', [
    'subject' => 'Maintenance tonight',
    'body' => '<p>…</p>',
    'url' => '/assets/42',
]);
```

## REST API

| Method & path | Description |
|---|---|
| `GET /notifications?page=1&limit=20&unread=1` | List the current user's in-app notifications |
| `GET /notifications/unread-count` | Unread count |
| `POST /notifications/{id}/read` | Mark one as read |
| `POST /notifications/read-all` | Mark all as read |
| `GET /notification-preferences` | List effective preferences |
| `PUT /notification-preferences` | Update preferences (`{"items":[{"topic","channel","enabled"}]}`) |

## Extending

- **Contact resolution**: replace `SubscriberInfoProviderInterface` (default:
  `KeycloakSubscriberInfoProvider`) to source contact info elsewhere.
- **Channels**: implement `Alchemy\NotifierBundle\Channel\ChannelInterface`; it is
  auto-tagged and picked up by the registry.
- **Audiences**: implement `Alchemy\NotifierBundle\Subscriber\UserDirectoryInterface`
  (see [Broadcasting](#broadcasting)); it is auto-tagged and picked up by the registry.
