import {useTranslation} from 'react-i18next';
import {Stat} from '../../../types.ts';
import Box from '@mui/material/Box/Box';
import Typography from '@mui/material/Typography/Typography';

type Props = {
    data: Stat | null;
    type: string | undefined;
};

export default function AssetMatomoMetricsList({data, type}: Props) {
    const {t} = useTranslation();

    if (data !== null) {
        const dlStyles = {
            display: 'grid',
            gridGap: '14px 16px',
            gridTemplateColumns: 'max-content',
        };

        const dtStyles = {
            fontWeight: 'lighter',
        };

        const ddStyles = {
            marginLeft: 20,
            gridColumnStart: 2,
        };

        if (type?.startsWith('video/') || type?.startsWith('audio/')) {
            return (
                <Box
                    sx={{
                        p: 2,
                    }}
                >
                    <dl style={dlStyles}>
                        <dt style={dtStyles}>
                            {t('matomo.assetView.nb_plays', 'play count')}
                        </dt>
                        <dd style={ddStyles}>{data.nb_plays}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_unique_visitors_plays',
                                'unique visitor plays count'
                            )}
                        </dt>
                        <dd style={ddStyles}>
                            {data.nb_unique_visitors_plays}
                        </dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_impressions',
                                'impressions count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.nb_impressions}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_unique_visitors_impressions',
                                'unique visitor impression count'
                            )}
                        </dt>
                        <dd style={ddStyles}>
                            {data.nb_unique_visitors_impressions}
                        </dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_finishes',
                                'finishes count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.nb_finishes}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.sum_time_progress',
                                'sum time progress'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.sum_time_progress}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_plays_with_tip',
                                'plays with tip count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.nb_plays_with_tip}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_plays_with_ml',
                                'plays with ml'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.nb_plays_with_ml}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.sum_fullscreen_plays',
                                'sum fullscreen play'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.sum_fullscreen_plays}</dd>
                        <dt style={dtStyles}>
                            {t('matomo.assetView.play_rate', 'play rate')}
                        </dt>
                        <dd style={ddStyles}>{data.play_rate}</dd>
                        <dt style={dtStyles}>
                            {t('matomo.assetView.finish_rate', 'finishe rate')}
                        </dt>
                        <dd style={ddStyles}>{data.finish_rate}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.fullscreen_rate',
                                'fullscreen rate'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.fullscreen_rate}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.avg_time_watched',
                                'avg time watched'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.avg_time_watched}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.avg_completion_rate',
                                'avg completion rate'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.avg_completion_rate}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.avg_media_length',
                                'avg media lenght'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.avg_media_length}</dd>
                    </dl>
                </Box>
            );
        } else {
            return (
                <Box
                    sx={{
                        p: 2,
                    }}
                >
                    <dl style={dlStyles}>
                        <dt style={dtStyles}>
                            {t('matomo.assetView.nb_visits', 'visits count')}
                        </dt>
                        <dd style={ddStyles}>{data.nb_visits}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_impressions',
                                'impressions count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.nb_impressions}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_interactions',
                                'interactions count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.nb_interactions}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.sum_daily_nb_uniq_visitors',
                                'sum daily unique visitor'
                            )}
                        </dt>
                        <dd style={ddStyles}>
                            {data.sum_daily_nb_uniq_visitors}
                        </dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.interaction_rate',
                                'interaction rate'
                            )}
                        </dt>
                        <dd style={ddStyles}>{data.interaction_rate}</dd>
                    </dl>
                </Box>
            );
        }
    } else {
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
}
