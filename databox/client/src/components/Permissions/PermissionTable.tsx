import {
    DisplayedPermissions,
    OnMaskChange,
    OnPermissionDelete,
} from './permissions';
import {Ace, UserType} from '../../types';
import {useTranslation} from 'react-i18next';
import {AclPermission, aclPermissions} from '../Acl/acl';
import AclHeader from '../Acl/AclHeader';
import {Box} from '@mui/material';
import PermissionRow from './PermissionRow';
import type {TFunction} from '@alchemy/i18n';
import PermissionRowSkeleton from './PermissionRowSkeleton';

export type PermissionHelpers = {
    [perm: string]: {
        label?: string;
        description?: string;
    };
};

type Props = {
    permissions: Ace[] | undefined;
    onMaskChange: OnMaskChange;
    onDelete: OnPermissionDelete;
    displayedPermissions?: DisplayedPermissions;
};

export default function PermissionTable({
    permissions,
    onMaskChange,
    onDelete,
    displayedPermissions,
}: Props) {
    const {t} = useTranslation();

    const columns = displayedPermissions
        ? Object.keys(aclPermissions).filter(c =>
              displayedPermissions.includes(c)
          )
        : Object.keys(aclPermissions);
    const hasAll = displayedPermissions
        ? displayedPermissions.includes(AclPermission.ALL)
        : true;

    const selectSize = 42;
    const actionsSize = 150;

    const allColumns = hasAll ? columns.concat([AclPermission.ALL]) : columns;
    return (
        <Box
            component={'table'}
            className={'acl-table'}
            sx={theme => ({
                'border': 'none',
                'width': '100%',
                'borderCollapse': 'separate',
                'borderSpacing': 0,
                '.ug, .a': {
                    p: 1,
                },
                '.p': {
                    'width': selectSize,
                    'maxWidth': selectSize,
                    'fontWeight': 400,
                    'textAlign': 'center',
                    '&:nth-child(even)': {
                        backgroundColor: theme.palette.grey[100],
                    },
                },
                'td': {
                    'verticalAlign': 'middle',
                    '.p': {
                        textAlign: 'center',
                    },
                },
                '.a': {
                    width: actionsSize,
                    maxWidth: actionsSize,
                },
                'th': {
                    'textAlign': 'left',
                    'verticalAlign': 'baseline',
                    '&.p': {
                        p: 1,
                        span: {
                            writingMode: 'vertical-rl',
                            margin: '0 auto',
                        },
                    },
                },
            })}
        >
            <thead>
                <tr>
                    <th className={'ug'}>
                        {t('acl.table.cols.user_group', `User/Group`)}
                    </th>
                    {allColumns.map(k => {
                        return (
                            <th key={k} className={'p'}>
                                <AclHeader aclName={k} />
                            </th>
                        );
                    })}
                    <th className={'a'}>
                        {t('permission_table.actions', `Actions`)}
                    </th>
                </tr>
            </thead>
            <tbody>
                {!permissions &&
                    [0, 1, 2].map(k => (
                        <PermissionRowSkeleton
                            permissions={allColumns}
                            key={k}
                        />
                    ))}
                {permissions &&
                    permissions.map(p => (
                        <PermissionRow
                            {...p}
                            all={hasAll}
                            permissions={columns}
                            onMaskChange={onMaskChange}
                            onDelete={onDelete}
                            userName={getUserName(p, t)}
                            key={p.id || `${p.userId}::${p.userType}`}
                        />
                    ))}
            </tbody>
        </Box>
    );
}

function getUserName(p: Ace, t: TFunction): string | undefined {
    const userId = p.userId;

    if (p.userType === UserType.User) {
        if (userId) {
            return (
                p.user?.username ??
                t('get_user_name.user_not_found', `User not found`)
            );
        }

        return t('get_user_name.all_users', `All users`);
    } else if (p.userType === UserType.Group) {
        if (userId) {
            return (
                p.group?.name ??
                t('get_user_name.group_not_found', `Group not found`)
            );
        }

        return t('get_user_name.all_groups', `All groups`);
    }
}
