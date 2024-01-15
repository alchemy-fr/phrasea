import {keycloakRealm, keycloakUrl} from "./urls";

const keycloakTokenUrl = `${keycloakUrl}/realms/${keycloakRealm}/protocol/openid-connect/token`;

export function getTokenClientCredentials(clientId, clientSecret) {
    return cy.request({
        method: 'POST',
        url: keycloakTokenUrl,
        body: {
            client_id: clientId,
            client_secret: clientSecret,
            grant_type: 'client_credentials',
            scope: 'publish',
        },
        form: true,
    }).then(res => {
        expect(res.body).to.have.property('access_token');

        return res.body.access_token;
    });
}
