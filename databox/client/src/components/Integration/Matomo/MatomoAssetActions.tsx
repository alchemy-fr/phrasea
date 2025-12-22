import {useEffect, useState} from 'react';
import {runIntegrationAction} from '../../../api/integrations.ts';
import {AssetIntegrationActionsProps} from '../types.ts';
import {useTranslation} from 'react-i18next';
import IntegrationPanelContent from '../Common/IntegrationPanelContent.tsx';
import {Typography} from '@mui/material';

type Props = {} & AssetIntegrationActionsProps;
type Stat = {
    nb_visits: number;
    nb_impressions: number;
    nb_interactions: number;
    sum_daily_nb_uniq_visitors: number;
    interaction_rate: string;
    nb_plays: number;
    nb_unique_visitors_plays: number;
    nb_unique_visitors_impressions: number;
    nb_finishes: number;
    sum_time_progress: number;
    nb_plays_with_tip: number;
    nb_plays_with_ml: number;
    sum_fullscreen_plays: number;
    play_rate: string;
    finish_rate: string;
    fullscreen_rate: string;
    avg_time_watched: string;
    avg_completion_rate: string;
    avg_media_length: string;
};

export default function MatomoAssetActions({
    asset,
    file,
    integration,
    expanded,
}: Props) {
    const {t} = useTranslation();

    const [stats, setStats] = useState<Stat | null>(null);
    const type = file.type;

    useEffect(() => {
        const process = async () => {
            const res = await runIntegrationAction('process', integration.id, {
                trackingId: asset.trackingId,
                type: file.type,
            });

            if (Object.hasOwnProperty.call(res, 'nb_impressions')) {
                setStats(res);
            }
        };

        if (expanded) {
            process();
        }
    }, [expanded]);

    if (stats !== null) {
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
                <IntegrationPanelContent>
                    <dl style={dlStyles}>
                        <dt style={dtStyles}>
                            {t('matomo.assetView.nb_plays', 'play count')}
                        </dt>
                        <dd style={ddStyles}>{stats.nb_plays}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_unique_visitors_plays',
                                'unique visitor plays count'
                            )}
                        </dt>
                        <dd style={ddStyles}>
                            {stats.nb_unique_visitors_plays}
                        </dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_impressions',
                                'impressions count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.nb_impressions}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_unique_visitors_impressions',
                                'unique visitor impression count'
                            )}
                        </dt>
                        <dd style={ddStyles}>
                            {stats.nb_unique_visitors_impressions}
                        </dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_finishes',
                                'finishes count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.nb_finishes}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.sum_time_progress',
                                'sum time progress'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.sum_time_progress}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_plays_with_tip',
                                'plays with tip count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.nb_plays_with_tip}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_plays_with_ml',
                                'plays with ml'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.nb_plays_with_ml}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.sum_fullscreen_plays',
                                'sum fullscreen play'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.sum_fullscreen_plays}</dd>
                        <dt style={dtStyles}>
                            {t('matomo.assetView.play_rate', 'play rate')}
                        </dt>
                        <dd style={ddStyles}>{stats.play_rate}</dd>
                        <dt style={dtStyles}>
                            {t('matomo.assetView.finish_rate', 'finishe rate')}
                        </dt>
                        <dd style={ddStyles}>{stats.finish_rate}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.fullscreen_rate',
                                'fullscreen rate'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.fullscreen_rate}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.avg_time_watched',
                                'avg time watched'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.avg_time_watched}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.avg_completion_rate',
                                'avg completion rate'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.avg_completion_rate}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.avg_media_length',
                                'avg media lenght'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.avg_media_length}</dd>
                    </dl>
                </IntegrationPanelContent>
            );
        } else {
            return (
                <IntegrationPanelContent>
                    <dl style={dlStyles}>
                        <dt style={dtStyles}>
                            {t('matomo.assetView.nb_visits', 'visits count')}
                        </dt>
                        <dd style={ddStyles}>{stats.nb_visits}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_impressions',
                                'impressions count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.nb_impressions}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.nb_interactions',
                                'interactions count'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.nb_interactions}</dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.sum_daily_nb_uniq_visitors',
                                'sum daily uniq visitor'
                            )}
                        </dt>
                        <dd style={ddStyles}>
                            {stats.sum_daily_nb_uniq_visitors}
                        </dd>
                        <dt style={dtStyles}>
                            {t(
                                'matomo.assetView.interaction_rate',
                                'interaction rate'
                            )}
                        </dt>
                        <dd style={ddStyles}>{stats.interaction_rate}</dd>
                    </dl>
                </IntegrationPanelContent>
            );
        }
    } else {
        return (
            <IntegrationPanelContent>
                <Typography>
                    {t(
                        'matomo.assetView.noStats',
                        'No statistics are available for this asset'
                    )}
                </Typography>
            </IntegrationPanelContent>
        );
    }
}
