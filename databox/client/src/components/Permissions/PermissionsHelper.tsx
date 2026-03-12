import {PermissionDefinition} from './permissionsTypes.ts';
import {Box, Divider, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Props = {
    definitions: PermissionDefinition[];
};

export default function PermissionsHelper({definitions}: Props) {
    const {t} = useTranslation();

    const withDesc = definitions.filter(def => !!def.description);

    if (withDesc.length === 0) {
        return null;
    }

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
                    {withDesc.map(def => {
                        return (
                            <tr key={def.key}>
                                <th>{def.label}</th>
                                <td>{def.description}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </Box>
        </>
    );
}
