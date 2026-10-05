'use client';

import {
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
} from 'react';
import type Pusher from 'pusher-js';
import {useConfig} from '@/lib/config/ConfigProvider';
import {api} from '@/lib/api/http';
import {useAuth} from '@/lib/auth/AuthProvider';

export type RealtimeCallback = (data: any) => void;
type Unsubscribe = () => void;

type RealtimeContextValue = {
    enabled: boolean;
    subscribe: (
        channel: string,
        event: string,
        callback: RealtimeCallback
    ) => Unsubscribe;
};

const RealtimeContext = createContext<RealtimeContextValue>({
    enabled: false,
    subscribe: () => () => undefined,
});

function normalizeChannel(channel: string): string {
    return channel.replace(/[^a-z0-9_\-=@,.;]/gi, '.');
}

export function RealtimeProvider({children}: PropsWithChildren) {
    const config = useConfig();
    const {status} = useAuth();
    const pusherRef = useRef<Pusher | null>(null);
    const pusherPromise = useRef<Promise<Pusher> | null>(null);
    const subscriptions = useRef<
        Record<string, {channel: any; events: Record<string, number>}>
    >({});
    const enabled = !!config.realtime;

    const getPusher = useCallback(async (): Promise<Pusher> => {
        if (pusherRef.current) {
            return pusherRef.current;
        }
        if (!pusherPromise.current) {
            pusherPromise.current = import('pusher-js').then(
                ({default: PusherCtor}) => {
                    const [wsHost, wsPort] = config.realtime!.host.split(':');
                    const pusher = new PusherCtor(config.realtime!.key, {
                        wsHost,
                        wsPort: wsPort ? Number(wsPort) : 443,
                        wssPort: wsPort ? Number(wsPort) : 443,
                        forceTLS: true,
                        disableStats: true,
                        enabledTransports: ['ws'],
                        cluster: '',
                        channelAuthorization: {
                            transport: 'ajax',
                            endpoint: '',
                            customHandler: (
                                {socketId, channelName},
                                callback
                            ) => {
                                api.post<{auth: string; channel_data?: string}>(
                                    '/pusher/auth',
                                    {
                                        socket_id: socketId,
                                        channel_name: channelName,
                                    }
                                )
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
                    });
                    pusher.connection.bind('error', (err: unknown) => {
                        console.warn('[realtime] connection error', err);
                    });
                    pusherRef.current = pusher;

                    return pusher;
                }
            );
        }

        return pusherPromise.current;
    }, [config.realtime]);

    const subscribe = useCallback<RealtimeContextValue['subscribe']>(
        (channelName, event, callback) => {
            if (!enabled) {
                return () => undefined;
            }
            const name = normalizeChannel(channelName);
            let active = true;

            void getPusher().then(pusher => {
                if (!active) {
                    return;
                }
                const sub =
                    subscriptions.current[name] ??
                    (subscriptions.current[name] = {
                        channel: pusher.subscribe(name),
                        events: {},
                    });
                sub.events[event] = (sub.events[event] ?? 0) + 1;
                sub.channel.bind(event, callback);
            });

            return () => {
                active = false;
                const sub = subscriptions.current[name];
                if (!sub) {
                    return;
                }
                sub.channel.unbind(event, callback);
                sub.events[event] = (sub.events[event] ?? 1) - 1;
                if (sub.events[event] <= 0) {
                    delete sub.events[event];
                }
                if (Object.keys(sub.events).length === 0) {
                    pusherRef.current?.unsubscribe(name);
                    delete subscriptions.current[name];
                }
            };
        },
        [enabled, getPusher]
    );

    // Reconnect private channels after login
    useEffect(() => {
        if (status === 'authenticated' && pusherRef.current) {
            pusherRef.current.connect();
        }
    }, [status]);

    const value = useMemo(() => ({enabled, subscribe}), [enabled, subscribe]);

    return (
        <RealtimeContext.Provider value={value}>
            {children}
        </RealtimeContext.Provider>
    );
}

export function useRealtime(): RealtimeContextValue {
    return useContext(RealtimeContext);
}

/**
 * Subscribes to a channel event for the lifetime of the component.
 */
export function useChannelEvent(
    channel: string | undefined,
    event: string,
    callback: RealtimeCallback,
    enabled = true
): void {
    const {subscribe} = useRealtime();
    const cbRef = useRef(callback);
    cbRef.current = callback;

    useEffect(() => {
        if (!channel || !enabled) {
            return;
        }

        return subscribe(channel, event, data => cbRef.current(data));
    }, [channel, event, enabled, subscribe]);
}
