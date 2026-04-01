import React, {useCallback, useEffect, useMemo} from 'react';
import {Ace, Entity, UserType} from '../../types';
import UserSelect from '../Form/UserSelect';
import GroupSelect from '../Form/GroupSelect';
import {Grid2 as Grid} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import {
    AclPermission,
    aclPermissions,
    FilterPermissions,
    OnMaskChange,
    OnPermissionDelete,
    PermissionDefinition,
    PermissionDefinitionOverride,
    PermissionType,
} from './permissionsTypes.ts';
import PermissionTable from './PermissionTable';
import PermissionsHelper from './PermissionsHelper.tsx';
import useAclPermissionDefinitions from './useAclPermissionDefinitions.ts';

type Props = {
    loadPermissions: () => Promise<Ace[]>;
    updatePermission: OnMaskChange;
    deletePermission: OnPermissionDelete;
    onListChanged?: (permissions: Ace[]) => void;
    definitions?: PermissionDefinitionOverride[];
    displayChildPermissions?: boolean;
    filterDefinitions?: FilterPermissions;
    helper?: boolean;
};

export default function PermissionList({
    loadPermissions,
    updatePermission,
    deletePermission,
    onListChanged,
    definitions,
    filterDefinitions,
    displayChildPermissions,
    helper,
    ...rest
}: Props) {
    const allDefinitions = useAclPermissionDefinitions({definitions});

    const {columns, hasAll} = useMemo(() => {
        const columns: PermissionDefinition[] = definitions
            ? (definitions.map(
                  d =>
                      allDefinitions.find(
                          ad => ad.type === d.type && ad.key === d.key
                      ) ?? d
              ) as PermissionDefinition[])
            : !displayChildPermissions
              ? allDefinitions.filter(
                    def =>
                        def.type !== PermissionType.Mask ||
                        def.value < aclPermissions[AclPermission.CHILD_CREATE]
                )
              : allDefinitions;

        const hasAll = columns.length > 2;

        return {
            columns: filterDefinitions
                ? columns.filter(filterDefinitions)
                : columns,
            hasAll,
        };
    }, [
        allDefinitions,
        definitions,
        displayChildPermissions,
        filterDefinitions,
    ]);

    const [permissions, setPermissions] = React.useState<Ace[]>();
    const {t} = useTranslation();

    useEffect(() => {
        loadPermissions().then(p => setPermissions(p));
    }, []);

    useEffect(() => {
        if (permissions) {
            onListChanged && onListChanged(permissions);
        }
    }, [permissions]);

    const addEntry = async (
        entry: {type: UserType} & Entity,
        mask: number
    ): Promise<void> => {
        setPermissions(p =>
            (p ?? []).concat([
                {
                    mask: mask,
                    userId: entry.id,
                    userType: entry.type,
                    resolving: true,
                } as Ace,
            ])
        );

        const ace = await updatePermission(entry.type, entry.id, mask);

        setPermissions(p =>
            (p ?? []).map(p => (p.resolving && p.userId === entry.id ? ace : p))
        );
    };

    const onDelete: OnPermissionDelete = useCallback(
        async (userType: UserType, userId: string | null) => {
            deletePermission(userType, userId);

            setPermissions(p =>
                p!.filter(
                    (ace: Ace) =>
                        !(ace.userType === userType && ace.userId === userId)
                )
            );
        },
        [deletePermission]
    );

    const onSelectUser = (id: string) => {
        addEntry(
            {
                type: UserType.User,
                id,
            },
            1
        );
    };

    const onSelectGroup = (id: string) => {
        addEntry(
            {
                type: UserType.Group,
                id,
            },
            1
        );
    };

    return (
        <div>
            <Grid
                container
                spacing={2}
                sx={theme => ({
                    position: 'relative',
                    zIndex: theme.zIndex.tooltip,
                })}
            >
                <Grid size={6}>
                    <FormRow>
                        <GroupSelect
                            placeholder={t(
                                'acl.form.user_select.placeholder',
                                `Select group`
                            )}
                            clearOnSelect={true}
                            onChange={option => {
                                option && onSelectGroup(option.value);
                            }}
                            disabledValues={permissions
                                ?.filter(
                                    ace =>
                                        ace.userType === 'group' && ace.userId
                                )
                                .map(ace => ace.userId!)}
                        />
                    </FormRow>
                </Grid>
                <Grid size={6}>
                    <FormRow>
                        <UserSelect
                            placeholder={t(
                                'acl.form.group_select.placeholder',
                                `Select user`
                            )}
                            clearOnSelect={true}
                            onChange={option => {
                                option && onSelectUser(option.value);
                            }}
                            disabledValues={permissions
                                ?.filter(
                                    ace => ace.userType === 'user' && ace.userId
                                )
                                .map(ace => ace.userId!)}
                        />
                    </FormRow>
                </Grid>
            </Grid>
            <PermissionTable
                {...rest}
                definitions={columns}
                hasAll={hasAll}
                permissions={permissions}
                onMaskChange={updatePermission}
                onDelete={onDelete}
            />

            {helper ? <PermissionsHelper definitions={columns} /> : null}
        </div>
    );
}
