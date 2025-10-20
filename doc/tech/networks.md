# Private/public networks

## Use cases

### Phraseanet in a private network, Expose/Uploader in a public one:

Given a private network where Phraseanet (core) lives.
We need to provide an Uploader to external employees (which can't access the private network).

In that case, Uploader service can't talk to Phraseanet in order to push new upload commits.
Pull mode is required: Phraseanet needs to fetch new commits regularly.
At this point, we need to authorize Phraseanet to list Uploader's commits.

The solution is to run a Keycloak service in the public network and to declare 
Phraseanet as a OAuth client with the grant_type `client_credentials` (M2M authentication).
So Phraseanet will be able to ask Keycloak an access_token and to authenticate beside Uploader.

![External Uploader](./external-uploader.png "External Uploader")

```sequence
title External Uploader

note over Phraseanet: Private network
note over Keycloak: Public network
note over Uploader: Public network

alt Need to get commit list
Phraseanet->Keycloak: GET https://keycloak.public.com/oauth/token
note left of Keycloak: {"grant_type":"client_credential",...}
Keycloak->Phraseanet: Access token response
note right of Phraseanet: {"access_token":"s3cr3t!token",...}
Phraseanet->Uploader: GET https://uploader.public.com/commits
note left of Uploader: Authorization: Bearer s3cr3t!token
Uploader->Phraseanet: Commit list response
note right of Phraseanet: [{"id":"46.."}]
```
