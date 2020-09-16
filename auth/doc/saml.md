# Configuring SAML Identity provider

Auth service will act as a Service Provider (SP) and can be registered to an Identity Provider (IdP).

Here follow the SP variables:

**Entity ID**: `https://{AUTH_BASE_URL}/saml/metadata/{idp}`

> Where `{idp}` is the identity provider name declared in config.json

**Assertion URL**: `https://{AUTH_BASE_URL}/saml/acs`

**Assertion Binding**: `urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST`

**Logout URL**: `https://{AUTH_BASE_URL}/saml/logout`

**Logout Binding**: `urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect`
