'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {getAssetMetrics} from '@/lib/api/assets';
import {InlineLoader} from '@/components/ui/loader';

const keys: [string, string][] = [
    ['nb_plays', 'Plays'],
    ['nb_finishes', 'Finishes'],
    ['play_rate', 'Play rate'],
    ['finish_rate', 'Finish rate'],
    ['fullscreen_rate', 'Fullscreen rate'],
    ['nb_visits', 'Visits'],
    ['nb_impressions', 'Impressions'],
    ['avg_time_watched', 'Avg. time watched'],
    ['avg_completion_rate', 'Avg. completion'],
];

export function AssetMetrics({assetId}: {assetId: string}) {
    const {t} = useTranslation();
    const metrics = useQuery({
        queryKey: ['asset-metrics', assetId],
        queryFn: () => getAssetMetrics(assetId),
    });

    if (metrics.isLoading) {
        return <InlineLoader />;
    }
    if (!metrics.data) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('asset.metrics.empty', 'No metrics available')}
            </p>
        );
    }

    return (
        <dl className="grid grid-cols-2 gap-2 text-sm">
            {keys
                .filter(([k]) => metrics.data![k] !== undefined)
                .map(([k, label]) => (
                    <div key={k} className="rounded-md bg-muted/50 p-2">
                        <dt className="text-xs text-muted-foreground">
                            {t(`asset.metrics.${k}`, label)}
                        </dt>
                        <dd className="font-medium tabular-nums">
                            {String(metrics.data![k])}
                        </dd>
                    </div>
                ))}
        </dl>
    );
}
