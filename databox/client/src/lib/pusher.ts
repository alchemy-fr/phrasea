import {
    createPusher,
    PusherEventCallback,
    registerPusherWs,
} from '@alchemy/core';
import React from 'react';
import {config} from '../init.ts';

const pusher = createPusher({
    key: config.pusherKey!,
    host: config.pusherHost!,
    onConnectionError: err => console.error(err),
});

export function registerWs(
    channel: string,
    event: string,
    callback: PusherEventCallback
) {
    return registerPusherWs(pusher, channel, event, callback);
}

export function useChannelRegistration(
    channel: string,
    event: string,
    callback: PusherEventCallback,
    when: boolean = true
) {
    React.useEffect(() => {
        if (when) {
            return registerWs(channel, event, callback);
        }
    }, [channel, event, when]);
}
