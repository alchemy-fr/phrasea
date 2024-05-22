import {
    createPusher,
    PusherEventCallback,
    registerPusherWs,
} from '@alchemy/core';
import config from '../config.ts';
import React from 'react';
import {toast} from 'react-toastify';

const pusher = createPusher({
    key: config.pusherKey!,
    host: config.pusherHost!,
    onConnectionError: err => toast.error(err.toString()),
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
