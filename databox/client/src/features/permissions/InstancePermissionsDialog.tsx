'use client';

import {ComponentProps, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {InfoIcon, Trash2Icon, UserIcon, UsersIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Group, User, UserType} from '@/types/api';
import {deleteAce, getTypeAces, putAce} from '@/lib/api/misc';
import {getUserDisplayName} from '@/lib/users';
import {Checkbox} from '@/components/ui/controls';
import {Button} from '@/components/ui/button';
import {Skeleton} from '@/components/ui/misc';
import {Tooltip} from '@/components/ui/overlays';
import {GroupSelect, UserSelect} from '@/components/form/selects';
import {cn} from '@/lib/utils/cn';
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
export function InstancePermissionsDialog({open, onOpenChange}: ModalProps) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const labels = getInstancePermissionLabels(t);
    const aces = useQuery({queryKey, queryFn: getInstanceAces});
    const [addType, setAddType] = useState<UserType>(UserType.Group);
    const [pending, setPending] = useState<InstancePermissionRow[]>([]);
    const [saving, setSaving] = useState<string | null>(null);

    const rows = useMemo(() => {
        const merged = mergeTypeAces(aces.data ?? []);
        const keys = new Set(merged.map(r => r.key));

        return [...merged, ...pending.filter(r => !keys.has(r.key))];
    }, [aces.data, pending]);

    const run = async (rowKey: string, writes: AceWrite[]) => {
        setSaving(rowKey);
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

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('instance_permissions.title', 'Instance permissions')}
            description={t(
                'instance_permissions.description',
                'Capabilities granted outside any workspace. Admins always have them.'
            )}
            hideCancel
            submitLabel={t('common.close', 'Close')}
            bodyClassName="space-y-4"
        >
            <div className="flex flex-wrap items-end gap-2">
                <div className="flex rounded-md border p-0.5">
                    <Button
                        variant={
                            addType === UserType.Group ? 'secondary' : 'ghost'
                        }
                        size="sm"
                        onClick={() => setAddType(UserType.Group)}
                    >
                        <UsersIcon /> {t('acl.group', 'Group')}
                    </Button>
                    <Button
                        variant={
                            addType === UserType.User ? 'secondary' : 'ghost'
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
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 text-left align-bottom font-medium">
                                    {t('acl.subject', 'User / group')}
                                </th>
                                {instancePermissions.map(p => (
                                    <th
                                        key={p.key}
                                        className="px-2 py-2 text-center align-bottom font-medium"
                                    >
                                        <Tooltip
                                            content={labels[p.key].description}
                                        >
                                            <VerticalLabel>
                                                {labels[p.key].label}
                                            </VerticalLabel>
                                        </Tooltip>
                                    </th>
                                ))}
                                <th className="w-10" />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map(row => {
                                const disabled = saving === row.key;

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
                                        {instancePermissions.map(p => (
                                            <td
                                                key={p.key}
                                                className="px-2 py-1.5 text-center"
                                            >
                                                <Checkbox
                                                    className="mx-auto"
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
                                                        run(row.key, [
                                                            getToggleWrite(
                                                                row,
                                                                p,
                                                                v === true
                                                            ),
                                                        ])
                                                    }
                                                />
                                            </td>
                                        ))}
                                        <td className="px-1 py-1.5">
                                            {row.userId ? (
                                                <Button
                                                    variant="ghost"
                                                    size="icon-xs"
                                                    className="text-destructive"
                                                    disabled={disabled}
                                                    onClick={() => remove(row)}
                                                    aria-label={t(
                                                        'common.delete',
                                                        'Delete'
                                                    )}
                                                >
                                                    <Trash2Icon />
                                                </Button>
                                            ) : null}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </FormDialog>
    );
}

/** Column header read bottom-to-top, so that the labels fit */
function VerticalLabel({className, ...props}: ComponentProps<'span'>) {
    return (
        <span
            className={cn(
                'inline-block rotate-180 text-left whitespace-nowrap [writing-mode:vertical-rl]',
                className
            )}
            {...props}
        />
    );
}
