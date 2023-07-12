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
  controller: Alchemy\CoreBundle\Controller\HealthCheckAction
  path: /_healthcheck
```

Ensure the route is not protected:
```yaml
security:
    access_control:
        - { path: ^/_healthcheck, roles: IS_AUTHENTICATED_ANONYMOUSLY }
```
