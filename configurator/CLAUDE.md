# CLAUDE.md — configurator

## Vendor configuration changes go in the synchronize step, never in a Doctrine migration

At each upgrade, `bin/migrate.sh` runs `bin/console synchronize` (`SynchronizeCommand`) before
`doctrine:migrations:migrate`: it replays `KeycloakConfigurator::synchronize()` and
`RabbitMqConfigurator::synchronize()` against the existing stack. A change to the Keycloak realm,
roles, clients or scopes (or to RabbitMQ) therefore reaches existing installs through that step:

- put it in a method called by both `configure()` (fresh install, `bin/setup.sh`) and
  `synchronize()` (upgrade), e.g. `KeycloakConfigurator::configureGroupAdminRole()`;
- keep it idempotent (create or update, skip what is already there): it runs at every upgrade;
- do **not** add a Doctrine migration (`KeycloakMigrationInterface` / `KeycloakMigrationTrait`) to
  apply it to existing realms. The existing ones are history; new ones are not written.

`synchronize()` only sees what already exists from the install (realm, role hierarchy): what it
touches must have been created by `configure()` beforehand, or be created by the step itself.
