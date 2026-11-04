'use client';

import Link from 'next/link';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {PencilIcon, PlusIcon, Trash2Icon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert, EmptyState} from '@alchemy/next-ui/ui/misc';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {useConfirm} from '@alchemy/next-ui/ui/confirm';
import {Page} from '@/components/layout/AppShell';
import {
    deleteTargetParams,
    getTargetParams,
    getTargetParamsList,
} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {TargetParamsEditor, targetParamsQueryKey} from './TargetParamsEditor';
import {RequirePermission} from './RequirePermission';

export function TargetParamsListScreen() {
    return (
        <RequirePermission permission="target_data">
            <TargetParamsList />
        </RequirePermission>
    );
}

function TargetParamsList() {
    const {t} = useTranslation();
    const confirm = useConfirm();
    const queryClient = useQueryClient();
    const {data, error, isPending} = useQuery({
        queryKey: targetParamsQueryKey,
        queryFn: getTargetParamsList,
    });

    return (
        <Page
            title={t('admin.target_params.title', 'Target Data')}
            actions={
                <Button asChild>
                    <Link
                        href={routes.targetParamsCreate()}
                        data-testid="target-params-create"
                    >
                        <PlusIcon />{' '}
                        {t(
                            'admin.target_params.create',
                            'Create New Target Params'
                        )}
                    </Link>
                </Button>
            }
        >
            {isPending ? (
                <FullPageLoader />
            ) : error ? (
                <Alert variant="destructive">{getErrorMessage(error)}</Alert>
            ) : data.length === 0 ? (
                <EmptyState
                    title={t(
                        'admin.target_params.empty',
                        'No target data yet.'
                    )}
                />
            ) : (
                <ul
                    data-testid="target-params-list"
                    className="divide-y rounded-lg border"
                >
                    {data.map(params => (
                        <li
                            key={params.id}
                            data-testid="target-params-row"
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="truncate font-medium">
                                    {params.target.name}
                                </div>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={routes.targetParamsEdit(params.id)}>
                                    <PencilIcon /> {t('admin.edit', 'Edit')}
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                data-testid="target-params-delete"
                                onClick={() =>
                                    void confirm({
                                        title: t(
                                            'admin.target_params.delete_title',
                                            'Delete Target Params'
                                        ),
                                        description: t(
                                            'admin.target_params.delete_confirm',
                                            'Delete the data of {{target}}?',
                                            {target: params.target.name}
                                        ),
                                        confirmLabel: t(
                                            'admin.delete',
                                            'Delete'
                                        ),
                                        destructive: true,
                                        onConfirm: async () => {
                                            await deleteTargetParams(params.id);
                                            await queryClient.invalidateQueries(
                                                {
                                                    queryKey:
                                                        targetParamsQueryKey,
                                                }
                                            );
                                        },
                                    })
                                }
                            >
                                <Trash2Icon /> {t('admin.delete', 'Delete')}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </Page>
    );
}

export function TargetParamsCreateScreen() {
    const {t} = useTranslation();

    return (
        <RequirePermission permission="target_data">
            <Page
                title={t('admin.target_params.new', 'New Target Params')}
                wide
            >
                <TargetParamsEditor />
            </Page>
        </RequirePermission>
    );
}

export function TargetParamsEditScreen({id}: {id: string}) {
    return (
        <RequirePermission permission="target_data">
            <TargetParamsEdit id={id} />
        </RequirePermission>
    );
}

function TargetParamsEdit({id}: {id: string}) {
    const {t} = useTranslation();
    const {data, error, isPending} = useQuery({
        queryKey: [...targetParamsQueryKey, id],
        queryFn: () => getTargetParams(id),
        // The editor starts from what is saved, never from a stale copy
        staleTime: 0,
    });

    if (isPending) {
        return <FullPageLoader />;
    }
    if (error) {
        return (
            <Page>
                <Alert variant="destructive">{getErrorMessage(error)}</Alert>
            </Page>
        );
    }

    return (
        <Page
            title={t('admin.target_params.edit', 'Target Data of {{target}}', {
                target: data.target.name,
            })}
            wide
        >
            <TargetParamsEditor key={data.id} params={data} />
        </Page>
    );
}
