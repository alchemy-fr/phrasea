'use client';

import {getUserDisplayName} from '@/lib/users';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {HelpCircleIcon, UserIcon, UsersIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Ace} from '@/types/api';
import {UserType} from '@/types/api';
import {deleteAce, getAces, putAce} from '@/lib/api/misc';
import {PermissionCheckbox} from './PermissionCheckbox';
import {ConfirmDeleteButton} from './ConfirmDeleteButton';
import {Button} from '@alchemy/next-ui/ui/button';
import {Skeleton} from '@alchemy/next-ui/ui/misc';
import {
    Tooltip,
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@alchemy/next-ui/ui/overlays';
import {GroupSelect, UserSelect} from '@/components/form/selects';
import {
    AclExtraPermission,
    aclMasks,
    PermissionDefinition,
    PermissionObject,
} from './permissionTypes';
import {cn} from '@alchemy/next-ui/utils/cn';
import {
    SlantedBand,
    SlantedLabel,
    slantedCellClassName,
    slantedColumnClassName,
    slantedTableClassName,
    useColumnHover,
    useSlantedHeader,
} from './SlantedHeader';

type Props = {
    objectType: PermissionObject;
    objectId: string;
    definitions: PermissionDefinition[];
    readOnly?: boolean;
    /** Mask of a newly added user or group */
    defaultMask?: number;
};

/**
 * Access control list editor: one row per user / group, one column per
 * permission plus an "All" column. Wildcard rows (all users / groups) are
 * displayed read-only.
 */
export function AclEditor({
    objectType,
    objectId,
    definitions,
    readOnly,
    defaultMask = aclMasks.VIEW,
}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const queryKey = ['aces', objectType, objectId];
    const aces = useQuery({
        queryKey,
        queryFn: () => getAces(objectType, objectId),
    });
    const [addType, setAddType] = useState<UserType>(UserType.Group);
    // Row being saved, and the column of the checkbox that changed
    const [saving, setSaving] = useState<{
        row: string;
        column?: string;
    } | null>(null);
    const headerRef = useSlantedHeader([
        aces.isLoading,
        definitions.map(d => d.label).join(),
        t,
    ]);
    const {column, highlight, tableProps} = useColumnHover();

    const refresh = () => queryClient.invalidateQueries({queryKey});

    const save = async (
        ace: Pick<Ace, 'userType' | 'userId'>,
        mask: number,
        metadata: number[],
        column?: string
    ) => {
        setSaving({row: `${ace.userType}:${ace.userId}`, column});
        try {
            await putAce({
                userType: ace.userType,
                userId: ace.userId,
                objectType,
                objectId,
                mask,
                metadata,
            });
            await refresh();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(null);
        }
    };

    const remove = async (ace: Ace) => {
        try {
            await deleteAce({
                userType: ace.userType,
                userId: ace.userId,
                objectType,
                objectId,
            });
            await refresh();
        } catch (e: any) {
            toast.error(e?.message);
        }
    };

    const add = (userId: string) => {
        if (
            aces.data?.some(a => a.userType === addType && a.userId === userId)
        ) {
            return;
        }
        void save({userType: addType, userId}, defaultMask, []);
    };

    const allMask = definitions.reduce(
        (acc, d) => (d.type === 'mask' ? acc | aclMasks[d.key] : acc),
        0
    );

    return (
        <div className="space-y-4">
            {!readOnly ? (
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
                                onChange={v => v && add(v)}
                            />
                        ) : (
                            <UserSelect
                                value={undefined}
                                onChange={v => v && add(v)}
                            />
                        )}
                    </div>
                    <Popover>
                        <PopoverTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                aria-label={t('acl.help', 'Permission levels')}
                            >
                                <HelpCircleIcon />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent
                            align="end"
                            className="w-96 space-y-2 text-sm"
                        >
                            <h4 className="font-semibold">
                                {t('acl.help', 'Permission levels')}
                            </h4>
                            <dl className="space-y-1">
                                {definitions.map(d => (
                                    <div key={`${d.type}-${d.key}`}>
                                        <dt className="font-medium">
                                            {d.label}
                                        </dt>
                                        {d.description ? (
                                            <dd className="text-xs text-muted-foreground">
                                                {d.description}
                                            </dd>
                                        ) : null}
                                    </div>
                                ))}
                            </dl>
                        </PopoverContent>
                    </Popover>
                </div>
            ) : null}

            {aces.isLoading ? (
                <Skeleton className="h-32" />
            ) : (
                <div className="overflow-x-auto rounded-md border">
                    <table className={slantedTableClassName} {...tableProps}>
                        <thead className="bg-muted/50 text-xs text-muted-foreground">
                            <tr ref={headerRef}>
                                <th className="w-full px-3 py-2 text-left align-bottom font-medium">
                                    {t('acl.subject', 'User / group')}
                                </th>
                                <th className={slantedColumnClassName}>
                                    <SlantedBand highlighted={column === 1} />
                                    <SlantedLabel
                                        className={cn(
                                            column === 1 && 'text-foreground'
                                        )}
                                    >
                                        {t('acl.all', 'All')}
                                    </SlantedLabel>
                                </th>
                                {definitions.map((d, i) => (
                                    <th
                                        key={`${d.type}-${d.key}`}
                                        className={slantedColumnClassName}
                                    >
                                        <SlantedBand
                                            highlighted={column === i + 2}
                                        />
                                        <Tooltip
                                            content={d.description}
                                            disabled={!d.description}
                                        >
                                            <SlantedLabel
                                                className={cn(
                                                    column === i + 2 &&
                                                        'text-foreground'
                                                )}
                                            >
                                                {d.label}
                                            </SlantedLabel>
                                        </Tooltip>
                                    </th>
                                ))}
                                <th data-slanted-end className="relative w-10">
                                    <SlantedBand className="w-0" />
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {(aces.data ?? []).length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={definitions.length + 3}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        {t(
                                            'acl.empty',
                                            'No permission granted yet'
                                        )}
                                    </td>
                                </tr>
                            ) : null}
                            {(aces.data ?? []).map(ace => {
                                const key = `${ace.userType}:${ace.userId}`;
                                const wildcard = !ace.userId;
                                const rowSaving =
                                    saving?.row === key
                                        ? saving.column
                                        : undefined;
                                const disabled =
                                    readOnly || wildcard || saving?.row === key;
                                const metadata = ace.metadata ?? [];
                                const allChecked =
                                    (ace.mask & allMask) === allMask;
                                const someChecked = (ace.mask & allMask) !== 0;

                                return (
                                    <tr
                                        key={key}
                                        className={cn(
                                            'border-t',
                                            wildcard && 'opacity-60'
                                        )}
                                    >
                                        <td className="px-3 py-1.5">
                                            <span className="inline-flex items-center gap-1.5">
                                                {ace.userType ===
                                                UserType.Group ? (
                                                    <UsersIcon className="size-4 text-muted-foreground" />
                                                ) : (
                                                    <UserIcon className="size-4 text-muted-foreground" />
                                                )}
                                                {wildcard
                                                    ? ace.userType ===
                                                      UserType.Group
                                                        ? t(
                                                              'acl.all_groups',
                                                              'All groups'
                                                          )
                                                        : t(
                                                              'acl.all_users',
                                                              'All users'
                                                          )
                                                    : ((ace.user
                                                          ? getUserDisplayName(
                                                                ace.user
                                                            )
                                                          : undefined) ??
                                                      ace.group?.name ??
                                                      ace.userId)}
                                            </span>
                                        </td>
                                        <td
                                            className={cn(
                                                slantedCellClassName,
                                                highlight(1)
                                            )}
                                        >
                                            <PermissionCheckbox
                                                saving={rowSaving === 'all'}
                                                checked={
                                                    allChecked
                                                        ? true
                                                        : someChecked
                                                          ? 'indeterminate'
                                                          : false
                                                }
                                                disabled={disabled}
                                                onCheckedChange={() =>
                                                    save(
                                                        ace,
                                                        allChecked
                                                            ? 0
                                                            : ace.mask |
                                                                  allMask,
                                                        metadata,
                                                        'all'
                                                    )
                                                }
                                            />
                                        </td>
                                        {definitions.map((d, i) => {
                                            const checked =
                                                d.type === 'mask'
                                                    ? (ace.mask &
                                                          aclMasks[d.key]) !==
                                                      0
                                                    : metadata.includes(
                                                          d.key as AclExtraPermission
                                                      );
                                            const columnKey = `${d.type}-${d.key}`;

                                            return (
                                                <td
                                                    key={columnKey}
                                                    className={cn(
                                                        slantedCellClassName,
                                                        highlight(i + 2)
                                                    )}
                                                >
                                                    <PermissionCheckbox
                                                        saving={
                                                            rowSaving ===
                                                            columnKey
                                                        }
                                                        checked={checked}
                                                        disabled={disabled}
                                                        onCheckedChange={v => {
                                                            if (
                                                                d.type ===
                                                                'mask'
                                                            ) {
                                                                void save(
                                                                    ace,
                                                                    v
                                                                        ? ace.mask |
                                                                              aclMasks[
                                                                                  d
                                                                                      .key
                                                                              ]
                                                                        : ace.mask &
                                                                              ~aclMasks[
                                                                                  d
                                                                                      .key
                                                                              ],
                                                                    metadata,
                                                                    columnKey
                                                                );
                                                            } else {
                                                                void save(
                                                                    ace,
                                                                    ace.mask,
                                                                    v
                                                                        ? [
                                                                              ...metadata,
                                                                              d.key,
                                                                          ]
                                                                        : metadata.filter(
                                                                              m =>
                                                                                  m !==
                                                                                  d.key
                                                                          ),
                                                                    columnKey
                                                                );
                                                            }
                                                        }}
                                                    />
                                                </td>
                                            );
                                        })}
                                        <td className="border-l px-1 py-1.5 text-right">
                                            {!wildcard && !readOnly ? (
                                                <ConfirmDeleteButton
                                                    onConfirm={() =>
                                                        remove(ace)
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
        </div>
    );
}
