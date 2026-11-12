'use client';

import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {Page} from '@/components/layout/AppShell';
import {getTarget, getTargetData} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import {AdminTargetList, BackToList} from './AdminTargetList';
import {targetDataQueryKey} from './queryKeys';
import {RequirePermission} from './RequirePermission';
import {TargetDataEditor} from './TargetDataEditor';

export function TargetDataListScreen() {
    const {t} = useTranslation();

    return (
        <RequirePermission permission="target_data">
            <AdminTargetList
                title={t('admin.target_data.title', 'Target Data')}
                testId="target-data-list"
                has={target => target.hasData}
                hasLabel={t('admin.target_data.has_data', 'Data')}
                hasNotLabel={t('admin.target_data.no_data', 'No data')}
                href={target => routes.targetData(target.id)}
            />
        </RequirePermission>
    );
}

export function TargetDataEditScreen({targetId}: {targetId: string}) {
    return (
        <RequirePermission permission="target_data">
            <TargetDataEdit targetId={targetId} />
        </RequirePermission>
    );
}

function TargetDataEdit({targetId}: {targetId: string}) {
    const {t} = useTranslation();
    const target = useQuery({
        queryKey: [...targetsQueryKey, targetId],
        queryFn: () => getTarget(targetId),
    });
    const data = useQuery({
        queryKey: targetDataQueryKey(targetId),
        queryFn: () => getTargetData(targetId),
        staleTime: 0,
    });

    // The editor starts from what is saved, never from a cached copy
    if (target.isPending || data.isPending || !data.isFetchedAfterMount) {
        return <FullPageLoader />;
    }
    if (target.error || data.error) {
        return (
            <Page>
                <Alert variant="destructive">
                    {getErrorMessage(target.error ?? data.error)}
                </Alert>
            </Page>
        );
    }

    return (
        <Page
            title={t('admin.target_data.edit', 'Target Data of {{target}}', {
                target: target.data.name,
            })}
            actions={<BackToList href={routes.targetDataList()} />}
            wide
        >
            <TargetDataEditor targetId={targetId} data={data.data} />
        </Page>
    );
}
