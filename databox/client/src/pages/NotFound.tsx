import {useTranslation} from 'react-i18next';
import {Box, Button, Typography} from '@mui/material';
import {getPath} from '@alchemy/navigation';
import {Link} from 'react-router-dom';
import {routes} from '../routes.ts';

type Props = {};

export default function NotFound({}: Props) {
    const {t} = useTranslation();

    return (
        <Box
            sx={{
                display: 'flex',
                minHeight: '100vh',
                flexDirection: 'column',
                justifyContent: 'center',
                alignItems: 'center',
            }}
        >
            <Typography variant={'h1'}>
                {t('not_found.title', 'Page not found')}
            </Typography>
            <Typography
                variant={'body1'}
                sx={{
                    mt: 2,
                }}
            >
                {t(
                    'not_found.content',
                    'The page you are looking does not exist or has been removed'
                )}
            </Typography>
            <Button
                sx={{
                    mt: 2,
                }}
                component={Link}
                to={getPath(routes.app)}
            >
                {t('not_found.back_home', 'Back home')}
            </Button>
        </Box>
    );
}
