# Alchemy Notifier Bundle

A Symfony bundle to notify users across multiple channels (Email, SMS, In-App via
Pusher), with per-user preferences and object subscriptions.

Users are identified by their **Keycloak `userId` (UUID)**, which is the uniqueness
key throughout the bundle. The application owns the Twig templates (one per topic /
channel); the bundle provides the Doctrine entities, the delivery pipeline and a REST
API.

> This bundle supersedes the deprecated `alchemy/notify-bundle` (Novu based).

## Concepts

| Concept | Description |
|---|---|
| **Subscriber** | A notifiable user, keyed by `userId`. Contact info (email, phone, locale, name) is resolved from Keycloak on first use. |
| **Subscription** | A link between a subscriber and an object it follows, identified by `(objectType, objectId)`. |
| **Topic** | A notification kind (e.g. `asset.comment`), declared in config, delivered through one or more channels. |
| **Channel** | `email`, `sms` or `in_app`. |
| **Preference** | A per-subscriber opt-out for a `(topic, channel)` pair. Enabled by default. |
| **Notification** | A persisted in-app notification (history + unread badge). |

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
    # Channels used by topics that don't declare their own
    default_channels: [email, in_app]
    # Pusher channel/event for in-app notifications
    in_app_channel_prefix: 'private-user-'
    in_app_event: 'notification'
    topics:
        asset.comment:
            channels: [email, in_app]
            user_configurable: true
        account.security:
            channels: [email, sms, in_app]
            # not shown in the preferences UI (cannot be opted out)
            user_configurable: false
```

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

## Usage

```php
use Alchemy\NotifierBundle\Manager\NotifierManager;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;

// Notify a single user
$notifier->notifyUser($userId, 'asset.comment', [
    'assetTitle' => $asset->getTitle(),
    'author' => $author->getName(),
    'comment' => $message,
]);

// Subscribe a user to an object, then notify all its followers
$subscriptions->subscribe($userId, 'asset', $asset->getId());
$notifier->notifyObject('asset', $asset->getId(), 'asset.comment', $params, [
    'exclude_user_id' => $authorId, // don't notify the author
]);
```

By default sending is dispatched asynchronously through Messenger
(`Alchemy\NotifierBundle\Message\SendNotification`); route it to a transport as usual.
Pass `['sync' => true]` in `$options` to deliver inline.

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
