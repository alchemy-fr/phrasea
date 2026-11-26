# Core bundle

## Setup

```yaml
# config/packages/alchemy_core.yml
alchemy_core:
  app_url: '%env(MYAPP_URL)%'
```

### Healthcheck

Enable the feature:
```yaml
# config/packages/alchemy_core.yml
alchemy_core:
  healthcheck: ~
```

Add route:
```yaml
# config/routes/alchemy_core.yaml
alchemy_core_healthcheck:
    resource: Alchemy\CoreBundle\Controller\HealthCheckAction
    type: attribute
alchemy_core_version:
    resource: Alchemy\CoreBundle\Controller\VersionAction
    type: attribute
alchemy_core_sentry_test:
    resource: Alchemy\CoreBundle\Controller\SentryTestController
    type: attribute
```

`GET /_health/version` answers `{"version": "5.0.0", "commit": "<sha>"}`, read
from `PHRASEA_VERSION` (`dev` by default) and `PHRASEA_COMMIT` (`null` when
empty), also available as the `alchemy_core.version` and `alchemy_core.commit`
parameters.

Ensure the route is not protected:
```yaml
security:
    access_control:
        - { path: ^/_health/(check|version)$, roles: PUBLIC_ACCESS }
```
