---
title: 'Secrets and variables'
---

# Secrets and variables

The configuration of an integration refers to named values instead of holding them: credentials
saved as encrypted **secrets**, plain settings saved as **variables**, of a workspace or of the
whole instance. The automations keep the credentials of their actions encrypted with the same key
pair.

## Concepts

| Object                | Entity / source                                   | Stored                                | Returned by the API             |
| --------------------- | ------------------------------------------------- | ------------------------------------- | ------------------------------- |
| Secret                | `App\Entity\Integration\WorkspaceSecret`          | encrypted (`value`)                   | name only, never the value      |
| Variable              | `App\Entity\Integration\WorkspaceEnv`             | in clear (`value`)                    | name and value                  |
| System variable       | `PWI_NAME` environment variable of the API containers | environment of the instance       | name only (`/system-envs`)      |

- **Scope**: a secret or a variable belongs to a workspace, or to the instance when its
  `workspace` is `null` (used by the instance-wide integrations, managed by the administrators).
- **Name**: matches `^[A-Za-z_][A-Za-z0-9_]*$`, 100 characters at most, unique in its scope
  (a secret and a variable are unique separately; the instance scope is checked by validation,
  since SQL does not compare `NULL` workspaces). The name cannot change once created: a `PATCH`
  with another `name` answers 400, as renaming would break the references.
- A variable is shown in clear to the same users as the secrets: it never holds a sensitive value.
- The secrets and variables of a workspace are deleted with it (`WorkspaceDelete`).

## Encryption

`App\Security\Secrets\EncryptionManager` uses a libsodium sealed box
(`sodium_crypto_box_seal`), with a key pair of `sodium_crypto_box_keypair()`, both keys and the
encrypted values encoded in base64.

- The API holds the public key only (`SECRETS_PUBLIC_KEY`): `App\Doctrine\Listener\SecretListener`
  encrypts the plain value of a `WorkspaceSecret` on `prePersist`/`preUpdate`, and the API never
  decrypts it.
- The workers and the scheduler also hold the secret key (`SECRETS_SECRET_KEY`) and decrypt the
  secrets when they run an integration or an automation
  (`App\Security\Secrets\SecretsManager::decryptSecret()`).
- In a process without the secret key (the API), decrypting throws
  `SecretKeyUnavailableException`: `SecretsManager` never returns a placeholder, which a value
  compared to a request (a webhook key…) would then match.

| Variable (`.env`)            | Container variable   | Given to                                      |
| ---------------------------- | -------------------- | --------------------------------------------- |
| `DATABOX_SECRETS_PUBLIC_KEY` | `SECRETS_PUBLIC_KEY` | `databox-api-php`, `databox-worker`, `databox-scheduler` |
| `DATABOX_SECRETS_SECRET_KEY` | `SECRETS_SECRET_KEY` | `databox-worker`, `databox-scheduler` (and `databox-api-php` in `docker-compose.dev.yml`) |

:::warning
The default key pair of `.env` is public. Generate a pair of your own before saving any secret and
set both keys in `.env.local`: a value is sealed for one public key, so the secrets saved with
another pair cannot be decrypted by the new one and must be saved again.
:::

```bash
dc run --rm databox-api-php su app -c "bin/console app:secrets:generate-key-pair"
```

The command prints the public key, then the secret key.

## References in the integrations

A string value of the configuration of an integration refers to a secret or a variable as
`${NAME}` (or the older form `$NAME`), alone or within a text (`https://${HOST}/api`).
`App\Integration\IntegrationConfig` resolves the references of the first-level keys when the
integration reads its configuration, in a single pass (a resolved value is never resolved again),
through `App\Integration\Env\EnvResolver`, in the scope of the integration (its workspace, or the
instance for an instance-wide integration, never falling back from a workspace to the instance):

1. the secret of that name, decrypted;
2. the variable of that name;
3. the environment variable `PWI_NAME` of the container;
4. otherwise an empty string.

Several defaults of the integrations are references to system variables, e.g. `apiKey` of
Remove.bg (`${REMOVE_BG_API_KEY}`) or `baseUrl` of `phrasea.uploader` (`${UPLOADER_API_URL}`);
`docker-compose.yml` defines a few `PWI_*` variables (see
[Configuration](./configuration.md#integrations-instance-variables)).

Rules enforced by `IntegrationConfig`:

- **Keys read by the API** (`ConfigWidget::ATTR_READ_BY_API`, e.g. the `securityKey` of
  `phrasea.uploader` and `core.asset_import`): the API compares them to a request itself and cannot
  decrypt a secret, so they refer to a variable or hold the value; a reference to a secret is
  refused when the configuration is saved.
- **URL keys** (`ConfigWidget::ATTR_URL`): in a workspace integration, a URL set to another value
  than its default cannot hold a reference (`$` is refused).
- **System variables in a workspace**: shared by every workspace, they never reach a host chosen
  by an editor. A URL key left to its default always resolves them; any other key resolves
  them only when it is a secret key or keeps its default, and only while every URL key keeps a default that no
  secret nor variable of the workspace redirects. Elsewhere they give an empty string, with a
  `warning` log.
- What the API shows of a configuration holds the values as written (`getClientValue()`): the
  references only resolve server side.

The details per integration are in [Integrations](./integration/01_integrations.md#secrets--variables).

## Secrets of the automations

The secret options of the automation actions are not references: they are written in the action
configuration and kept encrypted with the same key pair by `App\Automation\ActionConfigSecretsHandler`:

| Action                           | Secret options                                                                                         |
| -------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `remote_export`, `share_export`  | `s3.secretAccessKey`, `sftp.password`, `sftp.privateKey`, `sftp.passphrase`, `ftp.password`            |
| `webhook`                        | `secret` (the HMAC-SHA256 key of the `X-Phrasea-Signature` header)                                       |

- Encrypted when the automation is written (`SavedSearchAutomationInputMapper`); an empty value
  keeps the stored one.
- Never output: the API returns the option as `null` with a boolean `…Set` flag
  (`passwordSet`, `secretSet`…); the validation of the API also works on the masked configuration.
- Decrypted by the worker running the automation (`AutomationRunner`).

See [Automations](./automations.md#secrets).

## What is masked

| Where                                  | Secret                                                                                     | Variable           |
| -------------------------------------- | ------------------------------------------------------------------------------------------ | ------------------ |
| API output (`WorkspaceSecretOutput`)   | `id`, `name`, `workspace` (IRI or `null`), dates: the value is write only                   | value returned     |
| Audit log                              | changes of the value logged as `***` (`#[AuditIgnore(mask: true)]`)                         | value logged       |
| Admin (**Integrations** → **Secrets**) | the value is a password field, on the forms only                                            | value shown        |
| Integration configuration              | a reference shows as written (`${NAME}`)                                                    | same               |
| Automation actions                     | `null` + `…Set` flag in the API; stored encrypted                                           | not applicable     |
| Workspace templates                    | names only; `app:workspace:export` exports the encrypted values on demand (importable on the same instance only); duplicating a workspace copies them | value exported |

A value typed in clear in a secret field of an integration (`ConfigWidget::ATTR_SECRET`) is not a
secret: it is stored as is in the integration configuration and returned to its editors
(`configValues`, `configYaml`). Save it as a secret and refer to it instead.

## API

| Endpoint                                                | Purpose                                                                                       |
| ------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `GET /workspace-secrets?workspace={id}`                 | Secrets of a workspace, by name (`limit`, 30 by default and 100 at most, `page`)              |
| `GET /workspace-secrets?global=true`                    | Secrets of the instance                                                                       |
| `GET /workspace-secrets/{id}`                           | One secret, without its value                                                                 |
| `POST /workspace-secrets`                               | Create: `workspace` (IRI, or `null` for the instance), `name`, `value` (required)             |
| `PATCH /workspace-secrets/{id}`                         | Replace the value (`value`; an empty one changes nothing)                                     |
| `DELETE /workspace-secrets/{id}`                        | Delete                                                                                        |
| `GET`, `POST /workspace-envs`, `GET`, `PATCH`, `DELETE /workspace-envs/{id}` | Same for the variables; `value` is required on creation, may be empty, and is returned |
| `GET /system-envs?workspace={id}` or `?global=true`     | Names of the non-empty `PWI_*` variables of the instance, without the prefix nor the value, sorted, not paginated |

A listing without `workspace` nor `global` answers 400. Deleting a secret or a variable still
referenced is allowed: its references then resolve to the next source.

## Client screens

- **Workspace**: the **Secrets & variables** tab of the workspace management dialog
  (`/workspaces/[id]/manage/secrets`), shown to the users who can edit the workspace.
- **Instance**: the **Instance secrets & variables** tab of the **Instance integrations** screen
  (`/admin/integrations`), for the administrators (`databox-admin` or `admin` role).

Both render `databox/client/src/features/secrets/SecretManager.tsx`, in two sections:

- **Secrets**: **New secret** (dialog **New secret**: **Name**, **Value**, **Create**); per secret,
  its name, the integrations of the scope that refer to it ("Used by …" or **Not used**, computed
  by the client from their configuration), **Replace the value** (dialog **Replace the value of the
  secret**, **New value**) and a delete button. The value is never shown again.
- **Variables**: **New variable**; per variable, its name, its value, its users, an edit button
  (**Edit the variable**) and a delete button. A secret of the same name takes precedence.

Deleting asks for a confirmation that lists the integrations still referring to the name.

In the configuration form of an integration, a text field switches between **Value** and
**Reference**. A reference picks a name among the **Secrets**, the **Variables** and the
**System** variables of the scope, or creates one (**New secret…**, **New variable…**); a line under
the field tells what the name is (**Secret: its value is never shown**, **Variable**, **System
variable of the instance**, **Not defined in this workspace**…). A secret field typed in clear
offers **Save as secret**, which creates the secret and replaces the value by its reference.

## Permissions

`App\Security\Voter\WorkspaceSecretVoter` grants the read, creation, edition and deletion of a
secret or a variable:

- of a workspace, to the users who can edit that workspace (`EDIT`): even the names are of use to
  its editors only;
- of the instance, to the administrators.

The listings check the same rights on their scope (`SecretScopeTrait`), `/system-envs` included.
The administrators also manage both in the admin (**Integrations** → **Secrets**, **Env Vars**).

## Configuration

`DATABOX_SECRETS_PUBLIC_KEY` and `DATABOX_SECRETS_SECRET_KEY` (see above and
[Configuration](./configuration.md#secrets)); the `PWI_*` environment variables of the API
containers.

## Related pages

- [Integrations](./integration/01_integrations.md)
- [Automations](./automations.md)
- [Audit log](./audit-log.md)
- [Configuration](./configuration.md)
