import {useKeycloakUrls} from '@alchemy/react-auth';
import {Button, Container, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {config, keycloakClient} from '../init.ts';

type Props = {};

export default function RequireLogin({}: Props) {
    const {t} = useTranslation();
    const {getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

    return (
        <Container
            sx={{
                textAlign: 'center',
                my: 3,
            }}
        >
            <Typography
                variant="h5"
                sx={{
                    mb: 3,
                }}
            >
                {t('require_login.intro', 'Please log in to access this page.')}
            </Typography>

            <Button variant="contained" href={getLoginUrl()}>
                {t('require_login.log_in', 'Log In')}
            </Button>
        </Container>
    );
}
