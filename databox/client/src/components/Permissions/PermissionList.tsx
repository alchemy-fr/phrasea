import React, {useCallback, useEffect} from 'react';
import {Ace, Entity, UserType} from '../../types';
import UserSelect from '../Form/UserSelect';
import GroupSelect from '../Form/GroupSelect';
import {Grid} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import {DisplayedPermissions, OnPermissionDelete} from './permissions';
import PermissionTable from './PermissionTable';

type Props = {
    displayedPermissions?: DisplayedPermissions;
    loadPermissions: () => Promise<Ace[]>;
    updatePermission: (
        userType: UserType,
        userId: string | null,
        mask: number
    ) => Promise<Ace>;
    deletePermission: (
        userType: UserType,
        userId: string | null
    ) => Promise<void>;
    onListChanged?: (permissions: Ace[]) => void;
};

export default function PermissionList({
    displayedPermissions,
    loadPermissions,
    updatePermission,
    deletePermission,
    onListChanged,
}: Props) {
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
            p!.concat([
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
            p!.map(p => (p.resolving && p.userId === entry.id ? ace : p))
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
                <Grid item md={6}>
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
                <Grid item md={6}>
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
                permissions={permissions}
                displayedPermissions={displayedPermissions}
                onMaskChange={updatePermission}
                onDelete={onDelete}
            />
        </div>
    );
}
