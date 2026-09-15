export const keycloakUrl = Cypress.env('KEYCLOAK_URL');
export const keycloakRealm = Cypress.env('KEYCLOAK_REALM_NAME');
export const exposeUrl = Cypress.env('EXPOSE_CLIENT_URL');
export const exposeApiUrl = Cypress.env('EXPOSE_API_URL');
export const adminUsername = Cypress.env('ADMIN_USERNAME');
export const adminPassword = Cypress.env('ADMIN_PASSWORD');

export const exposeAdminClientId = Cypress.env('EXPOSE_ADMIN_CLIENT_ID');
export const exposeAdminClientSecret = Cypress.env('EXPOSE_ADMIN_CLIENT_SECRET');

// Databox (Next.js client)
export const databoxNextUrl = Cypress.env('DATABOX_NEXT_CLIENT_URL');
export const databoxApiUrl = Cypress.env('DATABOX_API_URL');
export const databoxNextClientId = Cypress.env('DATABOX_NEXT_CLIENT_ID');
export const databoxAdminClientId = Cypress.env('DATABOX_ADMIN_CLIENT_ID');
export const databoxAdminClientSecret = Cypress.env('DATABOX_ADMIN_CLIENT_SECRET');
