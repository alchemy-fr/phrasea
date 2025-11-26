import Pusher, {Channel} from 'pusher-js';
import {PusherEventCallback, UnregisterWebSocket} from './types';
import type {Options} from 'pusher-js';

function normalizeChannel(channel: string): string {
    return channel.replace(/[^a-z0-9_\-=@,.;]/gi, '.');
}

export function createPusher({
    key,
    host,
    onConnectionError,
    options,
}: {
    key: string;
    host: string;
    onConnectionError?: (error: any) => void;
    options?: Partial<Options>;
}): Pusher {
    const pusher = new Pusher(key, {
        wsHost: host,
        wsPort: 443,
        forceTLS: true,
        disableStats: true,
        enabledTransports: ['ws'],
        cluster: '',
        ...(options ?? {}),
    });

    pusher.connection.bind('error', function (err: any) {
        console.error(err);
        onConnectionError && onConnectionError(err);
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
