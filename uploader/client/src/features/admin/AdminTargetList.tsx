'use client';

import Link from 'next/link';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {ArrowLeftIcon, PencilIcon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert, Badge, EmptyState} from '@alchemy/next-ui/ui/misc';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {Page} from '@/components/layout/AppShell';
import {getAllTargets} from '@/lib/api/uploader';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import type {Target} from '@/types/api';

/**
 * The targets, each with a badge telling whether it has what the screen
 * edits, and a link to its editor.
 */
export function AdminTargetList({
    title,
    testId,
    has,
    hasLabel,
    hasNotLabel,
    href,
}: {
    title: string;
    testId: string;
    has: (target: Target) => boolean;
    hasLabel: string;
    hasNotLabel: string;
    href: (target: Target) => string;
}) {
    const {t} = useTranslation();
    const {data, error, isPending} = useQuery({
        queryKey: targetsQueryKey,
        queryFn: getAllTargets,
    });

    return (
        <Page title={title}>
            {isPending ? (
                <FullPageLoader />
            ) : error ? (
                <Alert variant="destructive">{getErrorMessage(error)}</Alert>
            ) : data.length === 0 ? (
                <EmptyState
                    title={t('admin.targets.empty', 'No target yet.')}
                />
            ) : (
                <ul data-testid={testId} className="divide-y rounded-lg border">
                    {data.map(target => (
                        <li
                            key={target.id}
                            data-testid="admin-target-row"
                            className="flex items-center gap-3 px-4 py-3"
                        >
                            <div className="min-w-0 flex-1 truncate font-medium">
                                {target.name}
                            </div>
                            {has(target) ? (
                                <Badge
                                    variant="secondary"
                                    data-testid="admin-target-badge"
                                    data-state="on"
                                >
                                    {hasLabel}
                                </Badge>
                            ) : (
                                <Badge
                                    variant="muted"
                                    data-testid="admin-target-badge"
                                    data-state="off"
                                >
                                    {hasNotLabel}
                                </Badge>
                            )}
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={href(target)}
                                    data-testid="admin-target-edit"
                                >
                                    <PencilIcon /> {t('admin.edit', 'Edit')}
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </Page>
    );
}

/** Back to the list of the targets of an admin screen */
export function BackToList({href}: {href: string}) {
    const {t} = useTranslation();

    return (
        <Button variant="outline" asChild>
            <Link href={href} data-testid="admin-back">
                <ArrowLeftIcon /> {t('admin.back', 'Back')}
            </Link>
        </Button>
    );
}
