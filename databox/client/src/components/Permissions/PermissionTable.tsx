import {DisplayedPermissions, OnMaskChange, OnPermissionDelete, Permission} from "./permissions";
import {Group, User, UserType} from "../../types";
import {useTranslation} from "react-i18next";
import {AclPermission, aclPermissions} from "../Acl/acl";
import {Box} from "@mui/material";
import PermissionRow from "./PermissionRow";
import React from "react";
import PermissionRowSkeleton from "./PermissionRowSkeleton";

export default function PermissionTable({
    permissions,
    onMaskChange,
    onDelete,
    users,
    groups,
    displayedPermissions,
}: {
    permissions: Permission[] | undefined;
    users: User[] | undefined;
    groups: Group[] | undefined;
    onMaskChange: OnMaskChange;
    onDelete: OnPermissionDelete;
    displayedPermissions?: DisplayedPermissions;
}) {
    const {t} = useTranslation();

    const columns = displayedPermissions ? Object.keys(aclPermissions).filter(c => displayedPermissions.includes(c)) : Object.keys(aclPermissions);
    const hasAll = displayedPermissions ? displayedPermissions.includes(AclPermission.ALL) : true;

    const selectSize = 42;
    const actionsSize = 150;

    const allColumns = hasAll ? columns.concat([AclPermission.ALL]) : columns;
    return <Box
        component={'table'}
        className={'acl-table'}
        sx={theme => ({
            border: 'none',
            width: '100%',
            borderCollapse: 'separate',
            borderSpacing: 0,
            '.ug, .a': {
                p: 1,
            },
            '.p': {
                width: selectSize,
                maxWidth: selectSize,
                fontWeight: 400,
                textAlign: 'center',
                '&:nth-child(even)': {
                    backgroundColor: theme.palette.grey[100],
                },
            },
            'td': {
                verticalAlign: 'middle',
                '.p': {
                    textAlign: 'center',
                },
            },
            '.a': {
                width: actionsSize,
                maxWidth: actionsSize,
            },
            'th': {
                textAlign: 'left',
                verticalAlign: 'baseline',
                '&.p': {
                    p: 1,
                    'span': {
                        writingMode: 'vertical-rl',
                        margin: '0 auto',
                    }
                },
            },
        })}
    >
        <thead>
        <tr>
            <th
                className={'ug'}
            >
                {t('acl.table.cols.user_group', `User/Group`)}
            </th>
            {allColumns.map(k => {
                return <th
                    key={k}
                    className={'p'}
                >
                    <span>{k}</span>
                </th>
            })}
            <th className={'a'}>Actions</th>
        </tr>
        </thead>
        <tbody>
        {!permissions && [0, 1, 2].map(k => <PermissionRowSkeleton
            permissions={allColumns}
            key={k}/>)}
        {permissions && permissions.map((p) => <PermissionRow
            {...p}
            all={hasAll}
            permissions={columns}
            onMaskChange={onMaskChange}
            onDelete={onDelete}
            userName={users && groups ? getUserName(p.userType, p.userId, users, groups) : undefined}
            key={p.id || `${p.userId}::${p.userType}`}
        />)}
        </tbody>
    </Box>
}

function getUserName(userType: UserType, userId: string | null, users: User[], groups: Group[]): string | undefined {
    if (userType === UserType.User) {
        if (userId) {
            return users.find(i => i.id === userId)?.username;
        }

        return 'All users';
    } else if (userType === UserType.Group) {
        if (userId) {
            return groups.find(i => i.id === userId)?.name;
        }

        return 'All groups';
    }
}
