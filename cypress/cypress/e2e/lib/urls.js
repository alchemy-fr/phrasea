export const keycloakUrl = Cypress.expose('KEYCLOAK_URL');
export const keycloakRealm = Cypress.expose('KEYCLOAK_REALM_NAME');
export const exposeUrl = Cypress.expose('EXPOSE_CLIENT_URL');
export const exposeApiUrl = Cypress.expose('EXPOSE_API_URL');
export const adminUsername = Cypress.expose('ADMIN_USERNAME');
export const adminPassword = Cypress.expose('ADMIN_PASSWORD');

export const exposeAdminClientId = Cypress.expose('EXPOSE_ADMIN_CLIENT_ID');
export const exposeAdminClientSecret = Cypress.expose('EXPOSE_ADMIN_CLIENT_SECRET');

// Databox
export const databoxUrl = Cypress.expose('DATABOX_CLIENT_URL');
export const databoxApiUrl = Cypress.expose('DATABOX_API_URL');
export const databoxClientId = Cypress.expose('DATABOX_CLIENT_ID');
export const databoxAdminClientId = Cypress.expose('DATABOX_ADMIN_CLIENT_ID');
export const databoxAdminClientSecret = Cypress.expose('DATABOX_ADMIN_CLIENT_SECRET');
