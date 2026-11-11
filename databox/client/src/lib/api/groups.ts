import {createGroupsApi} from '@alchemy/next-ui/groups/createGroupsApi';
import {api} from './http';

/**
 * The management of the Keycloak groups, sent with the token of the user:
 * offered only while not impersonating (`canManageGroups`).
 */
export const groupsApi = createGroupsApi({
    get: (path, params, signal) => api.get(path, {params, signal}),
    post: (path, json) => api.post(path, json),
    put: (path, json) => api.put(path, json),
    delete: path => api.delete(path),
});
