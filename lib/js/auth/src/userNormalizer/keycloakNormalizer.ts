import {AuthUser, KeycloakUserInfoResponse, UserNormalizer} from "../types";

export type KeycloakUser = {} & AuthUser;

export const keycloakNormalizer: UserNormalizer<KeycloakUser, KeycloakUserInfoResponse> = (payload) => {
    return {
        id: payload.sub,
        groups: payload.groups,
        username: payload.preferred_username,
        roles: payload.roles,
    }
}
