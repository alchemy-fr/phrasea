'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {HelpCircleIcon, Trash2Icon, UserIcon, UsersIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Ace} from '@/types/api';
import {UserType} from '@/types/api';
import {deleteAce, getAces, putAce} from '@/lib/api/misc';
import {Checkbox} from '@/components/ui/controls';
import {Button} from '@/components/ui/button';
import {Skeleton} from '@/components/ui/misc';
import {
    Tooltip,
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/overlays';
import {GroupSelect, UserSelect} from '@/components/form/selects';
import {
    AclExtraPermission,
    aclMasks,
    PermissionDefinition,
    PermissionObject,
} from './permissionTypes';
import {cn} from '@/lib/utils/cn';

type Props = {
    objectType: PermissionObject;
    objectId: string;
    definitions: PermissionDefinition[];
    readOnly?: boolean;
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
}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const queryKey = ['aces', objectType, objectId];
    const aces = useQuery({
        queryKey,
        queryFn: () => getAces(objectType, objectId),
    });
    const [addType, setAddType] = useState<UserType>(UserType.User);
    const [saving, setSaving] = useState<string | null>(null);

    const refresh = () => queryClient.invalidateQueries({queryKey});

    const save = async (
        ace: Pick<Ace, 'userType' | 'userId'>,
        mask: number,
        metadata: number[]
    ) => {
        const key = `${ace.userType}:${ace.userId}`;
        setSaving(key);
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
        void save({userType: addType, userId}, aclMasks.VIEW, []);
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
                                addType === UserType.User
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            size="sm"
                            onClick={() => setAddType(UserType.User)}
                        >
                            <UserIcon /> {t('acl.user', 'User')}
                        </Button>
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
                    </div>
                    <div className="min-w-64 flex-1">
                        {addType === UserType.User ? (
                            <UserSelect
                                value={undefined}
                                onChange={v => v && add(v)}
                            />
                        ) : (
                            <GroupSelect
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
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    {t('acl.subject', 'User / group')}
                                </th>
                                <th className="px-2 py-2 text-center font-medium">
                                    {t('acl.all', 'All')}
                                </th>
                                {definitions.map(d => (
                                    <th
                                        key={`${d.type}-${d.key}`}
                                        className="px-2 py-2 text-center font-medium whitespace-nowrap"
                                    >
                                        <Tooltip
                                            content={d.description}
                                            disabled={!d.description}
                                        >
                                            <span>{d.label}</span>
                                        </Tooltip>
                                    </th>
                                ))}
                                <th className="w-10" />
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
                                const disabled =
                                    readOnly || wildcard || saving === key;
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
                                                    : (ace.user?.username ??
                                                      ace.group?.name ??
                                                      ace.userId)}
                                            </span>
                                        </td>
                                        <td className="px-2 py-1.5 text-center">
                                            <Checkbox
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
                                                        metadata
                                                    )
                                                }
                                            />
                                        </td>
                                        {definitions.map(d => {
                                            const checked =
                                                d.type === 'mask'
                                                    ? (ace.mask &
                                                          aclMasks[d.key]) !==
                                                      0
                                                    : metadata.includes(
                                                          d.key as AclExtraPermission
                                                      );

                                            return (
                                                <td
                                                    key={`${d.type}-${d.key}`}
                                                    className="px-2 py-1.5 text-center"
                                                >
                                                    <Checkbox
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
                                                                    metadata
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
                                                                          )
                                                                );
                                                            }
                                                        }}
                                                    />
                                                </td>
                                            );
                                        })}
                                        <td className="px-1 py-1.5">
                                            {!wildcard && !readOnly ? (
                                                <Button
                                                    variant="ghost"
                                                    size="icon-xs"
                                                    className="text-destructive"
                                                    onClick={() => remove(ace)}
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
        </div>
    );
}
