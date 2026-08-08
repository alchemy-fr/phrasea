import Pusher, {Channel} from 'pusher-js';
import {PusherEventCallback, UnregisterWebSocket} from './types';
import type {Options} from 'pusher-js';

function normalizeChannel(channel: string): string {
    return channel.replace(/[^a-z0-9_\-=@,.;]/gi, '.');
}

export type ChannelAuthorizationData = {
    auth: string;
    channel_data?: string;
    shared_secret?: string;
};

/**
 * Authorizes a subscription to a private/presence channel. Given the connection
 * socket id and the channel name, it must resolve to the signature returned by
 * the backend auth endpoint. Only called by pusher-js for `private-`/`presence-`
 * prefixed channels; public channels never trigger it.
 */
export type ChannelAuthorizer = (params: {
    socketId: string;
    channelName: string;
}) => Promise<ChannelAuthorizationData>;

export function createPusher({
    key,
    host,
    onConnectionError,
    authorize,
    options,
}: {
    key: string;
    host: string;
    onConnectionError?: (error: any) => void;
    authorize?: ChannelAuthorizer;
    options?: Partial<Options>;
}): Pusher {
    const pusher = new Pusher(key, {
        wsHost: host,
        wsPort: 443,
        forceTLS: true,
        disableStats: true,
        enabledTransports: ['ws'],
        cluster: '',
        ...(authorize
            ? {
                  channelAuthorization: {
                      // `transport`/`endpoint` are required by the type but
                      // ignored when a customHandler is provided.
                      transport: 'ajax',
                      endpoint: '',
                      customHandler: ({socketId, channelName}, callback) => {
                          authorize({socketId, channelName})
                              .then(data => callback(null, data))
                              .catch(err =>
                                  callback(
                                      err instanceof Error
                                          ? err
                                          : new Error(String(err)),
                                      null
                                  )
                              );
                      },
                  },
              }
            : {}),
        ...(options ?? {}),
    });

    pusher.connection.bind('error', function (err: any) {
        // eslint-disable-next-line no-console
        console.error(err);
        onConnectionError?.(err);
    });

    return pusher;
}

type ChannelSubscription = {
    channel: Channel;
    events: Record<string, number>;
};

const subscribedChannels: Record<string, ChannelSubscription> = {};

export function registerPusherWs(
    pusher: Pusher,
    channelName: string,
    event: string,
    callback: PusherEventCallback
): UnregisterWebSocket {
    channelName = normalizeChannel(channelName);
    if (!(pusher as any).connecting) {
        (pusher as any).connecting = true;
        pusher.connection.bind('connected', (e: any) => {
            // eslint-disable-next-line no-console
            console.debug('connected', e);
        });
        pusher.connect();
    }

    const sub =
        subscribedChannels[channelName] ??
        (subscribedChannels[channelName] = {
            channel: pusher.subscribe(channelName),
            events: {},
        });

    sub.events[event] ??= 0;
    sub.events[event]++;
    sub.channel.bind(event, callback);

    return () => {
        const sub = subscribedChannels[channelName];
        if (sub) {
            sub.events[event]--;
            if (sub.events[event] === 0) {
                pusher.unsubscribe(channelName);
                delete subscribedChannels[channelName];
            } else {
                sub.channel.unbind(event, callback);
            }
        } else {
            pusher.unsubscribe(channelName);
        }
    };
}
