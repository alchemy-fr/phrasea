# Security Policy

## Supported versions

| Version      | Supported                          |
| ------------ | ---------------------------------- |
| 5.x (`next`) | Next major version, in development |
| 4.5.x        | Yes                                |
| < 4.5        | No                                 |

Security fixes are released for the latest 4.5.x version. Please upgrade to it before reporting a
vulnerability found in an older version.

## Reporting a vulnerability

**Do not open a public issue, pull request or discussion for a security vulnerability.**

Report it privately through GitHub Private Vulnerability Reporting:
[Security tab → Report a vulnerability](https://github.com/alchemy-fr/phrasea/security/advisories/new).

Please include as much of the following as you can:

- The affected component (Databox, Expose, Uploader, Dashboard, Configurator, Report, Indexer,
  Keycloak configuration…) and version
- The type of vulnerability and its impact
- Step-by-step instructions to reproduce it, with a proof of concept if possible
- The configuration needed to reproduce it (deployment mode, relevant environment variables)
- Any suggested fix or mitigation

## What to expect

The maintainers will acknowledge your report as soon as possible, investigate it, and keep you
informed of the progress through the private advisory. Once a fix is released, the advisory is
published; you will be credited unless you prefer to remain anonymous.

Please give the maintainers a reasonable time to release a fix before any public disclosure.
