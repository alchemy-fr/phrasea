import {useTranslation} from 'react-i18next';
import {MatomoMediaMetrics} from '../../../types.ts';
import Box from '@mui/material/Box/Box';
import Typography from '@mui/material/Typography/Typography';
import React from 'react';

type Props = {
    data: MatomoMediaMetrics | null;
    type: string | undefined;
    mediaPluginEnabled: boolean;
};

export default function AssetMatomoMetricsList({
    data,
    type,
    mediaPluginEnabled,
}: Props) {
    const {t} = useTranslation();

    if (!data) {
        return (
            <Box
                sx={{
                    p: 2,
                }}
            >
                <Typography>
                    {t(
                        'matomo.assetView.noStats',
                        'No statistics are available for this asset'
                    )}
                </Typography>
            </Box>
        );
    }

    const isMedia =
        mediaPluginEnabled &&
        (type?.startsWith('video/') || type?.startsWith('audio/'));

    let items: {
        label: string;
        value: string | number | null;
    }[];

    if (isMedia) {
        items = [
            {
                label: t('matomo.assetView.nb_plays', 'Play count'),
                value: data.nb_plays,
            },
            {
                label: t(
                    'matomo.assetView.nb_unique_visitors_plays',
                    'Unique visitor plays count'
                ),
                value: data.nb_unique_visitors_plays,
            },
            {
                label: t(
                    'matomo.assetView.nb_impressions',
                    'Impressions count'
                ),
                value: data.nb_impressions,
            },
            {
                label: t(
                    'matomo.assetView.nb_unique_visitors_impressions',
                    'Unique visitor impression count'
                ),
                value: data.nb_unique_visitors_impressions,
            },
            {
                label: t('matomo.assetView.nb_finishes', 'Finishes count'),
                value: data.nb_finishes,
            },
            {
                label: t(
                    'matomo.assetView.sum_time_progress',
                    'Sum time progress'
                ),
                value: data.sum_time_progress,
            },
            {
                label: t(
                    'matomo.assetView.nb_plays_with_tip',
                    'Plays with tip count'
                ),
                value: data.nb_plays_with_tip,
            },
            {
                label: t('matomo.assetView.nb_plays_with_ml', 'Plays with ml'),
                value: data.nb_plays_with_ml,
            },
            {
                label: t(
                    'matomo.assetView.sum_fullscreen_plays',
                    'Sum fullscreen play'
                ),
                value: data.sum_fullscreen_plays,
            },
            {
                label: t('matomo.assetView.play_rate', 'Play rate'),
                value: data.play_rate,
            },
            {
                label: t('matomo.assetView.finish_rate', 'Finish rate'),
                value: data.finish_rate,
            },
            {
                label: t('matomo.assetView.fullscreen_rate', 'Fullscreen rate'),
                value: data.fullscreen_rate,
            },
            {
                label: t(
                    'matomo.assetView.avg_time_watched',
                    'Avg time watched'
                ),
                value: data.avg_time_watched,
            },
            {
                label: t(
                    'matomo.assetView.avg_completion_rate',
                    'Avg completion rate'
                ),
                value: data.avg_completion_rate,
            },
            {
                label: t(
                    'matomo.assetView.avg_media_length',
                    'Avg media length'
                ),
                value: data.avg_media_length,
            },
        ];
    } else {
        items = [
            {
                label: t('matomo.assetView.nb_visits', 'Visits count'),
                value: data.nb_visits,
            },
            {
                label: t(
                    'matomo.assetView.nb_impressions',
                    'Impressions count'
                ),
                value: data.nb_impressions,
            },
            {
                label: t(
                    'matomo.assetView.nb_interactions',
                    'Interactions count'
                ),
                value: data.nb_interactions,
            },
            {
                label: t(
                    'matomo.assetView.sum_daily_nb_uniq_visitors',
                    'Sum daily unique visitor'
                ),
                value: data.sum_daily_nb_uniq_visitors,
            },
            {
                label: t(
                    'matomo.assetView.interaction_rate',
                    'Interaction rate'
                ),
                value: data.interaction_rate,
            },
        ];
    }

    return (
        <Box
            sx={{
                display: 'flex',
                flexDirection: 'column',
                gap: 1,
                [`.${Classes.Item}`]: {
                    'p': 1,
                    'borderBottom': theme =>
                        `1px solid ${theme.palette.divider}`,
                    '&:last-of-type': {
                        borderBottom: 'none',
                    },
                },
                [`.${Classes.Label}`]: {
                    fontWeight: 'lighter',
                },
                [`.${Classes.Value}`]: {
                    fontSize: '1.2rem',
                    ml: 0,
                },
            }}
        >
            {items.map((item, index) => (
                <div key={index} className={Classes.Item}>
                    <div className={Classes.Value}>{item.value}</div>
                    <div className={Classes.Label}>{item.label}</div>
                </div>
            ))}
        </Box>
    );
}

enum Classes {
    Item = 'metrics-item',
    Label = 'metrics-label',
    Value = 'metrics-value',
}
