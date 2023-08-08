# UPGRADE FROM 2.x to 3.0

```bash
bin/setup.sh \
  && dc restart keycloak \
  && dc run --rm configurator bin/console configure
  && dc run --rm configurator bin/console migration:20230807
```

# TODO Link Accounts: Attach migrated users to newly configured IdP
