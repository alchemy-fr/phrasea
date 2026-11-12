'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {InfoIcon, ShieldIcon, UserIcon, UsersIcon} from 'lucide-react';
import {toast} from 'sonner';
import {Group, User, UserType} from '@/types/api';
import {deleteAce, getTypeAces, putAce} from '@/lib/api/misc';
import {getUserDisplayName} from '@/lib/users';
import {PermissionCheckbox} from './PermissionCheckbox';
import {ConfirmDeleteButton} from './ConfirmDeleteButton';
import {Button} from '@alchemy/next-ui/ui/button';
import {EmptyState, Skeleton} from '@alchemy/next-ui/ui/misc';
import {Tooltip} from '@alchemy/next-ui/ui/overlays';
import {cn} from '@alchemy/next-ui/utils/cn';
import {GroupSelect, UserSelect} from '@/components/form/selects';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {usePageTrail} from '@/components/layout/layoutStore';
import {ScrollablePage} from '@/components/layout/ScrollablePage';
import {routes} from '@/lib/routes';
import {
    SlantedBand,
    SlantedLabel,
    slantedCellClassName,
    slantedColumnClassName,
    slantedTableClassName,
    useColumnHover,
    useSlantedHeader,
} from './SlantedHeader';
import {
    AceWrite,
    getInstancePermissionLabels,
    getRemoveRowWrites,
    getRowKey,
    getToggleWrite,
    hasInstancePermission,
    hasOtherPermissions,
    instancePermissionObjectTypes,
    instancePermissions,
    InstancePermissionRow,
    mergeTypeAces,
} from './instancePermissions';
import {instanceCapabilitiesQueryKey} from './useInstanceCapabilities';

const queryKey = ['instance-permissions'];

async function getInstanceAces() {
    return (
        await Promise.all(instancePermissionObjectTypes.map(getTypeAces))
    ).flat();
}

async function applyWrite(write: AceWrite): Promise<void> {
    if (!write) {
        return;
    }
    if (write.type === 'delete') {
        await deleteAce({
            userType: write.userType,
            userId: write.userId,
            objectType: write.objectType,
        });

        return;
    }
    await putAce({
        userType: write.userType,
        userId: write.userId,
        objectType: write.objectType,
        mask: write.mask,
        metadata: write.metadata,
    });
}

/**
 * Admin editor of the instance capabilities (granted outside any workspace):
 * one row per user / group, one column per capability. Each capability is a
 * bit of the ACE set on a whole object type, whose other bits are kept.
 */
export function InstancePermissionsScreen() {
    const {t} = useTranslation();
    const {hasRole} = useAuth();
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);
    const queryClient = useQueryClient();
    const labels = getInstancePermissionLabels(t);
    const aces = useQuery({
        queryKey,
        queryFn: getInstanceAces,
        enabled: isAdmin,
    });
    const [addType, setAddType] = useState<UserType>(UserType.Group);
    const [pending, setPending] = useState<InstancePermissionRow[]>([]);
    // Row being saved, and the column of the checkbox that changed
    const [saving, setSaving] = useState<{
        row: string;
        column?: string;
    } | null>(null);
    const headerRef = useSlantedHeader([aces.isLoading, t]);
    const {column, highlight, tableProps} = useColumnHover();

    const rows = useMemo(() => {
        const merged = mergeTypeAces(aces.data ?? []);
        const keys = new Set(merged.map(r => r.key));

        return [...merged, ...pending.filter(r => !keys.has(r.key))];
    }, [aces.data, pending]);

    const run = async (rowKey: string, writes: AceWrite[], column?: string) => {
        setSaving({row: rowKey, column});
        try {
            for (const write of writes) {
                await applyWrite(write);
            }
            await Promise.all([
                queryClient.invalidateQueries({queryKey}),
                queryClient.invalidateQueries({
                    queryKey: instanceCapabilitiesQueryKey,
                }),
            ]);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(null);
        }
    };

    const add = (userId: string, entity?: User | Group) => {
        const key = getRowKey(addType, userId);
        if (rows.some(r => r.key === key)) {
            return;
        }
        setPending(p => [
            ...p,
            {
                key,
                userType: addType,
                userId,
                ...(addType === UserType.Group
                    ? {group: entity as Group | undefined}
                    : {user: entity as User | undefined}),
                aces: {},
            },
        ]);
    };

    const remove = async (row: InstancePermissionRow) => {
        setPending(p => p.filter(r => r.key !== row.key));
        await run(row.key, getRemoveRowWrites(row));
    };

    const getRowLabel = (row: InstancePermissionRow): string => {
        if (!row.userId) {
            return row.userType === UserType.Group
                ? t('acl.all_groups', 'All groups')
                : t('acl.all_users', 'All users');
        }

        return (
            (row.user ? getUserDisplayName(row.user) : undefined) ??
            row.group?.name ??
            row.userId
        );
    };

    // Where the user is, shown in the top bar with the way back to the assets
    usePageTrail(
        t('instance_permissions.title', 'Instance permissions'),
        routes.instancePermissions()
    );

    if (!isAdmin) {
        return (
            <RequireAuth>
                <EmptyState
                    className="h-full"
                    title={t(
                        'common.forbidden',
                        'You are not allowed to access this page'
                    )}
                />
            </RequireAuth>
        );
    }

    return (
        <RequireAuth>
            <ScrollablePage
                data-testid="instance-permissions-screen"
                className="max-w-4xl"
            >
                <div>
                    <div className="flex items-center gap-3">
                        <ShieldIcon className="size-5 text-muted-foreground" />
                        <h1 className="text-lg font-semibold">
                            {t(
                                'instance_permissions.title',
                                'Instance permissions'
                            )}
                        </h1>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'instance_permissions.description',
                            'Capabilities granted outside any workspace. Admins always have them.'
                        )}
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex rounded-md border p-0.5">
                        <Button
                            variant={
                                addType === UserType.Group
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            size="sm"
                            onClick={() => setAddType(UserType.Group)}
                        >
                            <UsersIcon /> {t('acl.group', 'Group')}
                        </Button>
                        <Button
                            variant={
                                addType === UserType.User
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            size="sm"
                            onClick={() => setAddType(UserType.User)}
                        >
                            <UserIcon /> {t('acl.user', 'User')}
                        </Button>
                    </div>
                    <div className="min-w-64 flex-1">
                        {addType === UserType.Group ? (
                            <GroupSelect
                                value={undefined}
                                onChange={(v, groups) =>
                                    v &&
                                    add(
                                        v,
                                        groups?.find(g => g.id === v)
                                    )
                                }
                            />
                        ) : (
                            <UserSelect
                                value={undefined}
                                onChange={(v, users) =>
                                    v &&
                                    add(
                                        v,
                                        users?.find(u => u.id === v)
                                    )
                                }
                            />
                        )}
                    </div>
                </div>

                {aces.isLoading ? (
                    <Skeleton className="h-32" />
                ) : (
                    <div
                        className="overflow-x-auto rounded-md border"
                        data-testid="instance-permissions"
                    >
                        <table
                            className={slantedTableClassName}
                            {...tableProps}
                        >
                            <thead className="bg-muted/50 text-xs text-muted-foreground">
                                <tr ref={headerRef}>
                                    <th className="w-full px-3 py-2 text-left align-bottom font-medium">
                                        {t('acl.subject', 'User / group')}
                                    </th>
                                    {instancePermissions.map((p, i) => (
                                        <th
                                            key={p.key}
                                            className={slantedColumnClassName}
                                        >
                                            <SlantedBand
                                                highlighted={column === i + 1}
                                            />
                                            <Tooltip
                                                content={
                                                    labels[p.key].description
                                                }
                                            >
                                                <SlantedLabel
                                                    className={cn(
                                                        column === i + 1 &&
                                                            'text-foreground'
                                                    )}
                                                >
                                                    {labels[p.key].label}
                                                </SlantedLabel>
                                            </Tooltip>
                                        </th>
                                    ))}
                                    <th
                                        data-slanted-end
                                        className="relative w-10"
                                    >
                                        <SlantedBand className="w-0" />
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map(row => {
                                    const disabled = saving?.row === row.key;

                                    return (
                                        <tr
                                            key={row.key}
                                            className="border-t"
                                            data-testid={`instance-permissions-row-${row.key}`}
                                        >
                                            <td className="px-3 py-1.5">
                                                <span className="inline-flex items-center gap-1.5">
                                                    {row.userType ===
                                                    UserType.Group ? (
                                                        <UsersIcon className="size-4 text-muted-foreground" />
                                                    ) : (
                                                        <UserIcon className="size-4 text-muted-foreground" />
                                                    )}
                                                    {getRowLabel(row)}
                                                </span>
                                                {hasOtherPermissions(row) ? (
                                                    <Tooltip
                                                        content={t(
                                                            'instance_permissions.other_permissions_help',
                                                            'This user or group also has permissions on every basket, saved search, profile or workspace. They are kept, and managed from the administration (Global permissions).'
                                                        )}
                                                    >
                                                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                                            <InfoIcon className="size-3" />
                                                            {t(
                                                                'instance_permissions.other_permissions',
                                                                'Other permissions on all objects'
                                                            )}
                                                        </span>
                                                    </Tooltip>
                                                ) : null}
                                            </td>
                                            {instancePermissions.map((p, i) => (
                                                <td
                                                    key={p.key}
                                                    className={cn(
                                                        slantedCellClassName,
                                                        highlight(i + 1)
                                                    )}
                                                >
                                                    <PermissionCheckbox
                                                        saving={
                                                            disabled &&
                                                            saving?.column ===
                                                                p.key
                                                        }
                                                        aria-label={
                                                            labels[p.key].label
                                                        }
                                                        data-testid={`instance-permission-${p.key}`}
                                                        checked={hasInstancePermission(
                                                            row,
                                                            p
                                                        )}
                                                        disabled={disabled}
                                                        onCheckedChange={v =>
                                                            run(
                                                                row.key,
                                                                [
                                                                    getToggleWrite(
                                                                        row,
                                                                        p,
                                                                        v ===
                                                                            true
                                                                    ),
                                                                ],
                                                                p.key
                                                            )
                                                        }
                                                    />
                                                </td>
                                            ))}
                                            <td className="border-l px-1 py-1.5 text-right">
                                                {row.userId ? (
                                                    <ConfirmDeleteButton
                                                        disabled={disabled}
                                                        onConfirm={() =>
                                                            remove(row)
                                                        }
                                                    />
                                                ) : null}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </ScrollablePage>
        </RequireAuth>
    );
}
