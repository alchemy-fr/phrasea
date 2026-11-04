'use client';

import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery, useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import {
    PencilIcon,
    PlusIcon,
    SlidersHorizontalIcon,
    Trash2Icon,
} from 'lucide-react';
import type {PublicationProfile} from '@/types/api';
import {Button} from '@/components/ui/button';
import {EmptyState, Skeleton} from '@/components/ui/misc';
import {Tooltip} from '@/components/ui/overlays';
import {useConfirm} from '@/components/ui/confirm';
import {ErrorScreen} from '@/components/ErrorScreen';
import {deleteProfile, getProfiles, profileKeys} from '@/lib/api/publications';
import {routes} from '@/lib/routes';

export function ProfileList() {
    const {t} = useTranslation();
    const confirm = useConfirm();
    const queryClient = useQueryClient();
    const list = useInfiniteQuery({
        queryKey: [...profileKeys.list(), 'pages'],
        queryFn: ({pageParam}) => getProfiles(pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const profiles = list.data?.pages.flatMap(p => p.items) ?? [];

    const remove = (profile: PublicationProfile) =>
        confirm({
            title: t('profile.delete.title', 'Delete profile'),
            description: t(
                'profile.delete.description',
                'The profile "{{name}}" will be deleted. This cannot be undone.',
                {name: profile.name}
            ),
            confirmLabel: t('common.delete', 'Delete'),
            destructive: true,
            textToType: profile.name,
            onConfirm: async () => {
                await deleteProfile(profile.id);
                await queryClient.invalidateQueries({
                    queryKey: profileKeys.all,
                });
                toast.success(t('profile.delete.done', 'Profile deleted'));
            },
        });

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6 sm:px-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold tracking-tight">
                    {t('profiles.title', 'Profiles')}
                </h1>
                <Button asChild>
                    <Link
                        href={routes.profileCreate()}
                        data-testid="create-profile"
                    >
                        <PlusIcon /> {t('profiles.create', 'Create profile')}
                    </Link>
                </Button>
            </div>
            <p className="-mt-3 text-sm text-muted-foreground">
                {t(
                    'profiles.help',
                    'A profile holds settings shared by the publications using it.'
                )}
            </p>
            {list.isPending ? (
                <div className="flex flex-col gap-2">
                    {Array.from({length: 4}, (_, i) => (
                        <Skeleton key={i} className="h-14 rounded-lg" />
                    ))}
                </div>
            ) : list.isError ? (
                <ErrorScreen
                    error={list.error}
                    onRetry={() => list.refetch()}
                />
            ) : profiles.length === 0 ? (
                <EmptyState
                    testId="no-profile"
                    icon={<SlidersHorizontalIcon />}
                    title={t('profiles.empty', 'No profile')}
                />
            ) : (
                <ul
                    data-testid="profile-list"
                    className="divide-y rounded-lg border bg-card"
                >
                    {profiles.map(profile => {
                        const used = profile.publicationCount ?? 0;

                        return (
                            <li
                                key={profile.id}
                                data-testid="profile-item"
                                className="flex items-center gap-3 p-3"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="truncate font-medium">
                                        {profile.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {t('profiles.used_by', {
                                            count: used,
                                            defaultValue_one:
                                                'Used by {{count}} publication',
                                            defaultValue_other:
                                                'Used by {{count}} publications',
                                        })}
                                    </div>
                                </div>
                                {profile.capabilities?.edit !== false ? (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link
                                            href={routes.profileEdit(
                                                profile.id
                                            )}
                                            data-testid="profile-edit"
                                        >
                                            <PencilIcon />{' '}
                                            {t('common.edit', 'Edit')}
                                        </Link>
                                    </Button>
                                ) : null}
                                {profile.capabilities?.delete !== false ? (
                                    <Tooltip
                                        content={
                                            used > 0
                                                ? t(
                                                      'profiles.delete_used',
                                                      'A profile used by publications cannot be deleted.'
                                                  )
                                                : t('common.delete', 'Delete')
                                        }
                                    >
                                        {/* The tooltip of a disabled button needs a wrapper */}
                                        <span>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                className="text-destructive hover:text-destructive"
                                                aria-label={t(
                                                    'common.delete',
                                                    'Delete'
                                                )}
                                                data-testid="profile-delete"
                                                disabled={used > 0}
                                                onClick={() => remove(profile)}
                                            >
                                                <Trash2Icon />
                                            </Button>
                                        </span>
                                    </Tooltip>
                                ) : null}
                            </li>
                        );
                    })}
                </ul>
            )}
            {list.hasNextPage ? (
                <div className="flex justify-center">
                    <Button
                        variant="outline"
                        data-testid="load-more"
                        loading={list.isFetchingNextPage}
                        onClick={() => list.fetchNextPage()}
                    >
                        {t('common.load_more', 'Load more')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
