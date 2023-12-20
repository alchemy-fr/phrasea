import {Alert, Chip, Container, Grid, Typography, useMediaQuery, useTheme} from '@mui/material';
import Service from './Service';
import ClientApp from './ClientApp.tsx';
import config from './config.ts';
import ApiIcon from "@mui/icons-material/Api";
import SellIcon from '@mui/icons-material/Sell';
import keycloakImg from './images/keycloak.png'
import databoxImg from './images/databox.png'
import uploaderImg from './images/uploader.png'
import exposeImg from './images/expose.png'
import notifyImg from './images/notify.png'

type Props = {};

export default function Root({}: Props) {
    const {
        DATABOX_API_URL,
        EXPOSE_API_URL,
        UPLOADER_API_URL,
        NOTIFY_API_URL,
        KEYCLOAK_URL,
        DATABOX_CLIENT_URL,
        EXPOSE_CLIENT_URL,
        UPLOADER_CLIENT_URL,
        STACK_NAME,
        DEV_MODE,
        STACK_VERSION,
    } = config.env;

    console.debug('config.env', config.env);

    const theme = useTheme();
    const isLarge = useMediaQuery(theme.breakpoints.up('sm'));

    return (
        <Container>
            {isLarge && <Typography
                variant={'h1'}
                sx={{
                    '.MuiChip-root': {
                        ml: 2,
                        fontWeight: 400,
                    }
                }}
            >
                {STACK_NAME}
                <Chip
                    icon={<SellIcon/>}
                    label={STACK_VERSION}
                />
            </Typography>}

            {isLarge && DEV_MODE && <Alert
                sx={{
                    mt: 2,
                }}
                severity={'info'}
            >Developer Mode is enabled</Alert>}

            <Grid
                sx={{
                    pt: 2,
                }}
                container
                spacing={2}
            >
                <Service
                    mainUrl={KEYCLOAK_URL}
                    title={`Identity Manager`}
                    description={`Keycloak IAM`}
                    logo={keycloakImg}
                    links={[
                        {
                            icon: <ApiIcon />,
                            href: KEYCLOAK_URL,
                            title: `Keycloak Home`,
                        },
                    ]}
                />
                {DATABOX_API_URL && (
                    <ClientApp
                        apiUrl={DATABOX_API_URL}
                        clientUrl={DATABOX_CLIENT_URL}
                        title={`Databox`}
                        description={`Your DAM`}
                        logo={databoxImg}
                    />
                )}
                {EXPOSE_API_URL && (
                    <ClientApp
                        apiUrl={EXPOSE_API_URL}
                        clientUrl={EXPOSE_CLIENT_URL}
                        title={`Expose`}
                        description={`Share Publications`}
                        logo={exposeImg}
                    />
                )}
                {UPLOADER_API_URL && (
                    <ClientApp
                        apiUrl={UPLOADER_API_URL}
                        clientUrl={UPLOADER_CLIENT_URL}
                        title={`Uploader`}
                        description={`Standalone Asset deposit`}
                        logo={uploaderImg}
                    />
                )}
                {NOTIFY_API_URL && (
                    <Service
                        mainUrl={`${NOTIFY_API_URL}/admin`}
                        title={`Notify Admin`}
                        description={`Mail Sender`}
                        logo={notifyImg}
                        links={[
                            {
                                icon: <ApiIcon />,
                                href: NOTIFY_API_URL,
                                title: `API documentation of Notify`,
                            },
                        ]}
                    />
                )}
            </Grid>
        </Container>
    );
}
