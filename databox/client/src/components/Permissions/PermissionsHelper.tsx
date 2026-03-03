import {PermissionHelpers} from './permissions';
import {AclPermission} from '../Acl/acl';
import {Box, Divider, Typography} from '@mui/material';
import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';

type Props = {
    permissionHelper: PermissionHelpers;
};

export default function PermissionsHelper({permissionHelper}: Props) {
    const {t} = useTranslation();
    const perms = useMemo(() => {
        return Object.entries(permissionHelper).map(([key, value]) => {
            return {
                key: key as AclPermission,
                label: value.label,
                description: value.description,
            };
        });
    }, [permissionHelper]);

    return (
        <>
            <Divider
                sx={{
                    my: 2,
                }}
            />
            <Typography
                variant={'h6'}
                sx={{
                    mb: 2,
                }}
            >
                {t('permissions.helper_desc', 'Permission levels:')}
            </Typography>
            <Box
                component={'table'}
                sx={theme => ({
                    'th': {
                        textAlign: 'left',
                        backgroundColor: theme.palette.grey[100],
                    },
                    'th, td': {
                        p: 1,
                    },
                })}
            >
                <tbody>
                    {perms.map(({key, label, description}) => {
                        return (
                            <tr key={key}>
                                <th>{label}</th>
                                <td>{description}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </Box>
        </>
    );
}
