import Pusher from 'pusher-js';

export function createPusher({
        key,
        host,
    }: {
        key: string;
        host: string;
    }): Pusher {
    return new Pusher(key, {
        wsHost: host,
        wsPort: 443,
        forceTLS: true,
        disableStats: true,
        enabledTransports: ['ws'],
        cluster: '',
    });
}

export function registerWs() {
    // TODO
}
