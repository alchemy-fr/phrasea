import {KeycloakUser, KeycloakUserInfoResponse, UserNormalizer} from '../types';

export const keycloakNormalizer: UserNormalizer<
    KeycloakUser,
    KeycloakUserInfoResponse
> = payload => {
    return {
        id: payload.sub,
        groups: payload.groups,
        username: payload.preferred_username,
        roles: payload.roles,
    };
};
