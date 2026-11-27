# CLAUDE.md — configurator

## Vendor configuration changes go in the configure step, never in a Doctrine migration

At each install and each upgrade, `bin/lifecycle.sh` runs `bin/console configure` (with `--install`
on a fresh database only) before the Doctrine migrations: every `ConfiguratorInterface::configure()`
is replayed against the existing stack. A change to the Keycloak realm, roles, clients or scopes (or
to RabbitMQ, the buckets…) therefore reaches existing installs through that step:

- put it in `configure()` or a method it calls, e.g. `KeycloakConfigurator::configureGroupAdminRole()`;
- keep it idempotent (create or update, skip what is already there): it runs at every upgrade;
- what must only happen once (e.g. the default admin user) goes behind `ConfigureContext::$install`;
- do **not** add a Doctrine migration (`KeycloakMigrationInterface` / `KeycloakMigrationTrait`) to
  apply it to existing realms. The existing ones are history; new ones are not written.

See doc/tech/Deployment/lifecycle.md.
