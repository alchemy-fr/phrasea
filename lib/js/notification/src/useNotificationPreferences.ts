import React from 'react';
import type {HttpClient} from '@alchemy/api';
import {createNotificationApi} from './api';
import type {NotificationChannel, NotificationPreference} from './types';

type Options = {
    apiClient: HttpClient;
    /**
     * Whether the preferences panel is currently visible. Preferences are only
     * fetched lazily, the first time this becomes `true`.
     */
    active: boolean;
};

/**
 * Preferences grouped by topic, ready to render as one row per topic with a
 * toggle per channel. The channel order within a topic is preserved from the
 * backend response.
 */
export type NotificationPreferenceTopic = {
    topic: string;
    channels: {
        channel: NotificationChannel;
        enabled: boolean;
    }[];
};

export type UseNotificationPreferencesResult = {
    topics: NotificationPreferenceTopic[];
    loading: boolean;
    loaded: boolean;
    /** A preference toggle is in flight. */
    saving: boolean;
    /** The last load or save failed. */
    error: boolean;
    setPreference: (
        topic: string,
        channel: NotificationChannel,
        enabled: boolean
    ) => void;
    reload: () => void;
};

function groupByTopic(
    items: NotificationPreference[]
): NotificationPreferenceTopic[] {
    const topics: NotificationPreferenceTopic[] = [];
    const index = new Map<string, NotificationPreferenceTopic>();

    for (const item of items) {
        let group = index.get(item.topic);
        if (!group) {
            group = {topic: item.topic, channels: []};
            index.set(item.topic, group);
            topics.push(group);
        }
        group.channels.push({channel: item.channel, enabled: item.enabled});
    }

    return topics;
}

export function useNotificationPreferences({
    apiClient,
    active,
}: Options): UseNotificationPreferencesResult {
    const api = React.useMemo(
        () => createNotificationApi(apiClient),
        [apiClient]
    );

    const [items, setItems] = React.useState<NotificationPreference[]>([]);
    const [loading, setLoading] = React.useState(false);
    const [loaded, setLoaded] = React.useState(false);
    const [saving, setSaving] = React.useState(false);
    const [error, setError] = React.useState(false);

    const itemsRef = React.useRef(items);
    itemsRef.current = items;
    const loadingRef = React.useRef(false);

    const load = React.useCallback(
        async (signal?: AbortSignal) => {
            if (loadingRef.current) {
                return;
            }
            loadingRef.current = true;
            setLoading(true);
            setError(false);

            try {
                const result = await api.listPreferences(signal);
                if (signal?.aborted) {
                    return;
                }
                setItems(result);
                setLoaded(true);
            } catch (e) {
                if (!signal?.aborted) {
                    setError(true);
                }
                throw e;
            } finally {
                loadingRef.current = false;
                setLoading(false);
            }
        },
        [api]
    );

    // Lazily load the first time the panel becomes visible.
    //
    // Unlike the notification list (which mounts with `active=false` and only
    // loads later, on the false->true transition), this panel is mounted with
    // `active` already `true` — the popover is open when the user clicks the
    // settings icon. Under React 18 StrictMode the mount effect runs
    // mount -> cleanup -> mount; aborting the sole in-flight request on that
    // cleanup would leave the panel empty until a manual refresh. So we guard
    // with a ref that survives the StrictMode remount and deliberately do not
    // abort, letting the single request complete.
    const startedRef = React.useRef(false);
    React.useEffect(() => {
        if (!active || startedRef.current) {
            return;
        }
        startedRef.current = true;
        load().catch(() => {
            // Allow a retry (refresh button) after a failed initial load.
            startedRef.current = false;
        });
    }, [active, load]);

    const setPreference = React.useCallback(
        (
            topic: string,
            channel: NotificationChannel,
            enabled: boolean
        ): void => {
            const previous = itemsRef.current;
            const target = previous.find(
                i => i.topic === topic && i.channel === channel
            );
            if (!target || target.enabled === enabled) {
                return;
            }

            // Optimistic update.
            setItems(current =>
                current.map(i =>
                    i.topic === topic && i.channel === channel
                        ? {...i, enabled}
                        : i
                )
            );
            setSaving(true);
            setError(false);

            api.updatePreferences([{topic, channel, enabled}])
                .then(fresh => {
                    // Trust the server as the source of truth.
                    setItems(fresh);
                })
                .catch(() => {
                    // Roll back on failure.
                    setError(true);
                    setItems(previous);
                })
                .finally(() => {
                    setSaving(false);
                });
        },
        [api]
    );

    const reload = React.useCallback(() => {
        load().catch(() => {});
    }, [load]);

    const topics = React.useMemo(() => groupByTopic(items), [items]);

    return {
        topics,
        loading,
        loaded,
        saving,
        error,
        setPreference,
        reload,
    };
}
