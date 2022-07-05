import React, {useCallback, useEffect, useRef, useState} from 'react';
import {Ace, Group, User, UserType} from "../../types";
import {getGroups, getUsers} from "../../api/user";
import UserSelect from "../Form/UserSelect";
import GroupSelect from "../Form/GroupSelect";
import {Grid} from "@mui/material";
import FormRow from "../Form/FormRow";
import {useTranslation} from 'react-i18next';
import {DisplayedPermissions, OnPermissionDelete, Permission} from "./permissions";
import PermissionTable from "./PermissionTable";

type State = {
    permissions: Permission[];
    users: User[];
    groups: Group[];
};

type Props = {
    displayedPermissions?: DisplayedPermissions;
    loadPermissions: () => Promise<Permission[]>;
    updatePermission: (userType: UserType, userId: string | null, mask: number) => Promise<void>;
    deletePermission: (userType: UserType, userId: string | null) => Promise<void>;
};

export default function PermissionList({
                                           displayedPermissions,
                                           loadPermissions,
                                           updatePermission,
                                           deletePermission,
                                       }: Props) {
    const [data, setData] = useState<State>();
    const {t} = useTranslation();

    const resolveUsersPromise = useRef<(value: User[]) => void>();
    const resolveGroupsPromise = useRef<(value: Group[]) => void>();
    const usersPromise = useRef<Promise<User[]>>(new Promise((resolve) => {
        resolveUsersPromise.current = resolve;
    }));
    const groupsPromise = useRef<Promise<Group[]>>(new Promise((resolve) => {
        resolveGroupsPromise.current = resolve;
    }));

    useEffect(() => {
        Promise.all([
            loadPermissions(),
            getUsers(),
            getGroups(),
        ]).then(([permissions, users, groups]) => {
            resolveUsersPromise.current!(users);
            resolveGroupsPromise.current!(groups);

            // Set data must occur AFTER promises resolution
            setData({
                permissions,
                users,
                groups,
            });
        });
    }, []);

    const addEntry = (entry: { id: string, type: UserType }, mask: number): void => {
        updatePermission(entry.type, entry.id, mask);

        setData(p => {
            const aces = [...(p!.permissions ?? [])];

            aces.push({
                mask: mask,
                userId: entry.id,
                userType: entry.type,
            } as Ace);

            return {
                ...p!,
                permissions: aces,
            };
        });
    }

    const onDelete: OnPermissionDelete = useCallback(async (userType: UserType, userId: string | null) => {
        deletePermission(userType, userId);

        setData(p => {
            return {
                ...p!,
                permissions: p!.permissions.filter((ace: Ace) => !(ace.userType === userType && ace.userId === userId)),
            };
        });
    }, [deletePermission]);


    const onSelectUser = (id: string) => {
        addEntry({
            type: UserType.User,
            id,
        }, 1);
    }

    const onSelectGroup = (id: string) => {
        addEntry({
            type: UserType.Group,
            id,
        }, 1);
    }

    return <div>
        <Grid container spacing={2}
              sx={theme => ({
                  position: 'relative',
                  zIndex: theme.zIndex.tooltip,
              })}
        >
            <Grid item md={6}>
                <FormRow>
                    <GroupSelect
                        data={groupsPromise.current}
                        placeholder={t('acl.form.user_select.placeholder', `Select group`)}
                        clearOnSelect={true}
                        onChange={(option) => {
                            option && onSelectGroup(option.value);
                        }}
                        disabledValues={data?.permissions.filter(ace => ace.userType === 'group' && ace.userId).map(ace => ace.userId!)}
                    />
                </FormRow>
            </Grid>
            <Grid item md={6}>
                <FormRow>
                    <UserSelect
                        data={usersPromise.current}
                        placeholder={t('acl.form.group_select.placeholder', `Select user`)}
                        clearOnSelect={true}
                        onChange={(option) => {
                            option && onSelectUser(option.value)
                        }}
                        disabledValues={data?.permissions.filter(ace => ace.userType === 'user' && ace.userId).map(ace => ace.userId!)}
                    />
                </FormRow>
            </Grid>
        </Grid>
        <PermissionTable
            permissions={data?.permissions}
            users={data?.users}
            groups={data?.groups}
            displayedPermissions={displayedPermissions}
            onMaskChange={updatePermission}
            onDelete={onDelete}
        />
    </div>
}
