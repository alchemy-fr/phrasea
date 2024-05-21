import Pusher from 'pusher-js';
import {PusherEventCallback, UnregisterWebSocket} from "./types";
import type {Options} from "pusher-js";

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
        ...(options ?? {})
    });

    pusher.connection.bind('error', function (err: any) {
        console.error(err);
        onConnectionError && onConnectionError(err);
    });

    return pusher;
}

export function registerPusherWs(
    pusher: Pusher,
    channelName: string,
    event: string,
    callback: PusherEventCallback,
): UnregisterWebSocket {
    if (!(pusher as any).connecting) {
        (pusher as any).connecting = true;
        pusher.connection.bind('connected', (e: any) => {
            console.debug('connected', e);
        });
        pusher.connect();
    }

    const channel = pusher.subscribe(channelName);

    channel.bind(event, callback);

    return () => {
        pusher.unsubscribe(channelName);
    };
}
