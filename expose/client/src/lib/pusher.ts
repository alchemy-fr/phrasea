import {
    createPusher,
    PusherEventCallback,
    registerPusherWs,
} from '@alchemy/core';
import {apiClient, config} from '../init.ts';

const pusher = createPusher({
    key: config.pusherKey!,
    host: config.pusherHost!,
    // eslint-disable-next-line no-console
    onConnectionError: err => console.error(err),
    // Authorizes private channels (e.g. `private-user-{id}` notifications).
    // apiClient injects the Keycloak Bearer token; the backend verifies the
    // user owns the requested channel before signing.
    authorize: async ({socketId, channelName}) => {
        const {data} = await apiClient.post('/pusher/auth', {
            socket_id: socketId,
            channel_name: channelName,
        });

        return data;
    },
});

export function registerWs(
    channel: string,
    event: string,
    callback: PusherEventCallback
) {
    return registerPusherWs(pusher, channel, event, callback);
}
