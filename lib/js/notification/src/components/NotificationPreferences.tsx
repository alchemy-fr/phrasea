import React from 'react';
import {
    Alert,
    Box,
    CircularProgress,
    Divider,
    IconButton,
    Stack,
    ToggleButton,
    Tooltip,
    Typography,
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import EmailIcon from '@mui/icons-material/Email';
import NotificationsIcon from '@mui/icons-material/Notifications';
import NotificationsOffIcon from '@mui/icons-material/NotificationsOff';
import RefreshIcon from '@mui/icons-material/Refresh';
import SmsIcon from '@mui/icons-material/Sms';
import {useTranslation} from 'react-i18next';
import type {HttpClient} from '@alchemy/api';
import type {NotificationChannel} from '../types';
import {useNotificationPreferences} from '../useNotificationPreferences';

const CHANNEL_ICONS: Record<NotificationChannel, React.ReactNode> = {
    email: <EmailIcon fontSize="small" />,
    in_app: <NotificationsIcon fontSize="small" />,
    sms: <SmsIcon fontSize="small" />,
};

type LabelResolver = (key: string) => string;

type Props = {
    apiClient: HttpClient;
    /**
     * Whether the panel is visible. Preferences load lazily the first time
     * this is `true`. Defaults to `true` for standalone (e.g. settings-page)
     * usage; pass the popover-open state when embedded.
     */
    active?: boolean;
    /**
     * Optional overrides for topic/channel display names. When omitted, labels
     * are resolved via i18n (`notification.topic.<key>` /
     * `notification.channel.<channel>`) with a humanized fallback.
     */
    topicLabel?: LabelResolver;
    channelLabel?: (channel: NotificationChannel) => string;
    /**
     * When provided, a back arrow is shown in the header (used when the panel
     * is embedded next to the notification list, e.g. inside a popover).
     */
    onBack?: () => void;
    /**
     * Called whenever the panel's content changes size (loading -> loaded,
     * topics appearing, error banner). Lets an embedding container (e.g. a
     * popover) reposition itself so the grown panel stays within the viewport.
     */
    onResize?: () => void;
};

function humanize(key: string): string {
    return key
        .replace(/[:._-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/^\w/, c => c.toUpperCase());
}

export default function NotificationPreferences({
    apiClient,
    active = true,
    topicLabel,
    channelLabel,
    onBack,
    onResize,
}: Props) {
    const {t} = useTranslation();
    const {topics, loading, loaded, saving, error, setPreference, reload} =
        useNotificationPreferences({apiClient, active});

    // Notify the container after each content-affecting change so it can
    // reposition (the panel grows once the preferences finish loading).
    React.useEffect(() => {
        onResize?.();
    }, [loading, loaded, topics.length, error, onResize]);

    const resolveTopic = React.useCallback(
        (topic: string): string =>
            topicLabel?.(topic) ??
            t(`notification.topic.${topic}`, humanize(topic)),
        [t, topicLabel]
    );

    const resolveChannel = React.useCallback(
        (channel: NotificationChannel): string =>
            channelLabel?.(channel) ??
            t(`notification.channel.${channel}`, humanize(channel)),
        [t, channelLabel]
    );

    return (
        <Box sx={{display: 'flex', flexDirection: 'column', maxHeight: '70vh'}}>
            <Box
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    px: 2,
                    py: 1.5,
                }}
            >
                <Stack direction="row" alignItems="center" spacing={0.5}>
                    {onBack ? (
                        <Tooltip
                            title={t('notification.preferences.back', 'Back')}
                        >
                            <IconButton
                                size="small"
                                onClick={onBack}
                                edge="start"
                                aria-label={t(
                                    'notification.preferences.back',
                                    'Back'
                                )}
                            >
                                <ArrowBackIcon fontSize="small" />
                            </IconButton>
                        </Tooltip>
                    ) : null}
                    <Typography variant="h6" component="div">
                        {t(
                            'notification.preferences.title',
                            'Notification settings'
                        )}
                    </Typography>
                </Stack>
                <Stack direction="row" alignItems="center" spacing={0.5}>
                    {saving ? <CircularProgress size={16} /> : null}
                    <Tooltip
                        title={t('notification.preferences.refresh', 'Refresh')}
                    >
                        <span>
                            <IconButton
                                size="small"
                                onClick={reload}
                                disabled={loading}
                                aria-label={t(
                                    'notification.preferences.refresh',
                                    'Refresh'
                                )}
                            >
                                <RefreshIcon fontSize="small" />
                            </IconButton>
                        </span>
                    </Tooltip>
                </Stack>
            </Box>
            <Divider />

            <Box sx={{overflowY: 'auto', flexGrow: 1, px: 2, py: 1}}>
                {error ? (
                    <Alert severity="error" sx={{my: 1}}>
                        {t(
                            'notification.preferences.error',
                            'Could not update your notification settings.'
                        )}
                    </Alert>
                ) : null}

                {topics.map(({topic, channels}) => (
                    <Box key={topic}>
                        <Box
                            sx={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 2,
                                py: 1.5,
                            }}
                        >
                            <Typography variant="body2" sx={{fontWeight: 500}}>
                                {resolveTopic(topic)}
                            </Typography>
                            <Stack direction="row" spacing={1}>
                                {channels.map(({channel, enabled}) => {
                                    const label = resolveChannel(channel);

                                    return (
                                        <Tooltip title={label} key={channel}>
                                            <ToggleButton
                                                value={channel}
                                                selected={enabled}
                                                onChange={() =>
                                                    setPreference(
                                                        topic,
                                                        channel,
                                                        !enabled
                                                    )
                                                }
                                                size="small"
                                                aria-label={label}
                                            >
                                                {CHANNEL_ICONS[channel] ??
                                                    label}
                                            </ToggleButton>
                                        </Tooltip>
                                    );
                                })}
                            </Stack>
                        </Box>
                        <Divider />
                    </Box>
                ))}

                {loaded && topics.length === 0 && !loading ? (
                    <Box
                        sx={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            gap: 1,
                            color: 'text.secondary',
                            py: 5,
                        }}
                    >
                        <NotificationsOffIcon fontSize="large" />
                        <Typography variant="body2">
                            {t(
                                'notification.preferences.empty',
                                'No configurable notifications'
                            )}
                        </Typography>
                    </Box>
                ) : null}

                {loading && !loaded ? (
                    <Box
                        sx={{
                            display: 'flex',
                            justifyContent: 'center',
                            py: 3,
                        }}
                    >
                        <CircularProgress size={24} />
                    </Box>
                ) : null}
            </Box>
        </Box>
    );
}
