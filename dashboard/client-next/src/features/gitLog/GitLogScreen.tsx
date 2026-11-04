'use client';

import Link from 'next/link';
import {useSyncExternalStore} from 'react';
import {useTranslation} from 'react-i18next';
import {ArrowLeftIcon, GitCommitHorizontalIcon} from 'lucide-react';
import {EmptyState} from '@alchemy/next-ui/ui/misc';
import {useConfig} from '@/lib/config/ConfigProvider';
import {routes} from '@/lib/routes';
import type {Commit} from './gitLog';

const subscribe = () => () => undefined;

export function GitLogScreen({commits}: {commits: Commit[] | null}) {
    const {t, i18n} = useTranslation();
    const {stack} = useConfig();
    // Dates are formatted in the time zone of the browser, unknown to the
    // server: only once hydrated
    const hydrated = useSyncExternalStore(
        subscribe,
        () => true,
        () => false
    );
    const format = new Intl.DateTimeFormat(i18n.language, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-6">
            <Link
                href={routes.home()}
                className="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeftIcon className="size-4" />
                {t('git_log.back', 'Back to the dashboard')}
            </Link>
            <h1 className="text-2xl font-semibold">
                {t('git_log.title', 'Latest changes')}
                {stack.version ? (
                    <span className="ml-2 text-base font-normal text-muted-foreground">
                        {stack.version}
                    </span>
                ) : null}
            </h1>
            {commits && commits.length > 0 ? (
                <ol
                    data-testid="commits"
                    className="divide-y rounded-lg border"
                >
                    {commits.map(commit => {
                        const date = new Date(commit.date);

                        return (
                            <li
                                key={commit.hash}
                                className="flex flex-col gap-0.5 px-4 py-2.5"
                            >
                                <span className="font-medium break-words">
                                    {commit.subject}
                                </span>
                                <span className="flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                    <code>{commit.hash}</code>
                                    <span>{commit.author}</span>
                                    {!hydrated ||
                                    Number.isNaN(date.getTime()) ? null : (
                                        <time dateTime={commit.date}>
                                            {format.format(date)}
                                        </time>
                                    )}
                                </span>
                            </li>
                        );
                    })}
                </ol>
            ) : (
                <EmptyState
                    testId="no-commits"
                    icon={<GitCommitHorizontalIcon />}
                    title={t(
                        'git_log.empty',
                        'The history of changes is not available for this build.'
                    )}
                />
            )}
        </div>
    );
}
