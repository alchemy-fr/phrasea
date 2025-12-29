import {
    Alert,
    Chip,
    Container,
    Grid,
    Link,
    Typography,
    useMediaQuery,
    useTheme,
} from '@mui/material';
import Service from './Service';
import ClientApp from './ClientApp.tsx';
import config from './config.ts';
import SellIcon from '@mui/icons-material/Sell';
import keycloakImg from './images/keycloak.png';
import databoxImg from './images/databox.png';
import uploaderImg from './images/uploader.png';
import exposeImg from './images/expose.png';
import dashboardImg from './images/dashboard.png';
import DashboardBar from './DashboardBar';
import {useAuth} from '@alchemy/react-auth';
import AdminPanelSettingsIcon from '@mui/icons-material/AdminPanelSettings';
import {parseInlineStyle} from '@alchemy/core/src/declaration';

type Props = {};

export default function Dashboard({}: Props) {
    const theme = useTheme();
    const isLarge = useMediaQuery(theme.breakpoints.up('sm'));
    const {user} = useAuth();

    const {
        DATABOX_API_URL,
        EXPOSE_API_URL,
        UPLOADER_API_URL,
        DATABOX_CLIENT_URL,
        EXPOSE_CLIENT_URL,
        UPLOADER_CLIENT_URL,
        STACK_NAME,
        DEV_MODE,
        STACK_VERSION,
        PGADMIN_URL,
        ELASTICHQ_URL,
        MAILHOG_URL,
        MATOMO_URL,
        PHPMYADMIN_URL,
        RABBITMQ_CONSOLE_URL,
        TRAEFIK_CONSOLE_URL,
        SOKETI_USAGE_URL,
        NOVU_DASHBOARD_URL,
    } = config.env;

    const roles = user?.roles ?? [];
    const isInIframe = inIframe();

    return (
        <Container>
            {isLarge && (
                <DashboardBar>
                    <Typography
                        variant={'h1'}
                        sx={{
                            '.MuiChip-root': {
                                ml: 2,
                            },
                        }}
                    >
                        <div style={{display: 'flex', alignItems: 'center'}}>
                            {config.logo?.src ? (
                                <img
                                    src={config.logo!.src}
                                    alt={STACK_NAME}
                                    style={
                                        config.logo!.style
                                            ? parseInlineStyle(
                                                  config.logo!.style!
                                              )
                                            : {maxHeight: 48, maxWidth: 150}
                                    }
                                />
                            ) : (
                                STACK_NAME
                            )}

                            {user ? (
                                <Chip
                                    icon={<SellIcon />}
                                    label={STACK_VERSION}
                                    color={'info'}
                                    component="a"
                                    href="/git-log.html"
                                    target={'_blank'}
                                    clickable
                                />
                            ) : (
                                ''
                            )}
                        </div>
                    </Typography>
                </DashboardBar>
            )}

            {user && isLarge && DEV_MODE && (
                <Alert
                    sx={{
                        mt: 2,
                    }}
                    severity={'info'}
                >
                    Developer Mode is enabled
                </Alert>
            )}

            <Grid
                sx={{
                    pt: 2,
                }}
                container
                spacing={2}
            >
                {roles.includes('group-admin') ||
                roles.includes('user-admin') ? (
                    <Service
                        mainUrl={`${config.keycloakUrl}/admin/${config.realmName}/console`}
                        title={`Identity Manager`}
                        description={`Keycloak IAM`}
                        logo={keycloakImg}
                        links={
                            roles.includes('admin')
                                ? [
                                      {
                                          icon: <AdminPanelSettingsIcon />,
                                          href: `${config.keycloakUrl}/admin/master/console`,
                                          title: `Master Admin`,
                                      },
                                  ]
                                : undefined
                        }
                    />
                ) : (
                    ''
                )}
                {DATABOX_API_URL && (
                    <ClientApp
                        apiUrl={DATABOX_API_URL}
                        clientUrl={DATABOX_CLIENT_URL}
                        title={`Databox`}
                        description={`Your DAM`}
                        logo={databoxImg}
                        isAdmin={roles.includes('databox-admin')}
                    />
                )}
                {EXPOSE_API_URL && (
                    <ClientApp
                        apiUrl={EXPOSE_API_URL}
                        clientUrl={EXPOSE_CLIENT_URL}
                        title={`Expose`}
                        description={`Share Publications`}
                        logo={exposeImg}
                        isAdmin={roles.includes('expose-admin')}
                    />
                )}
                {UPLOADER_API_URL && (
                    <ClientApp
                        apiUrl={UPLOADER_API_URL}
                        clientUrl={UPLOADER_CLIENT_URL}
                        title={`Uploader`}
                        description={`Standalone Asset deposit`}
                        logo={uploaderImg}
                        isAdmin={roles.includes('uploader-admin')}
                    />
                )}
                {isInIframe ? (
                    <Service
                        mainUrl={`/`}
                        title={`Dashboard`}
                        description={`Phrasea entrypoint`}
                        logo={dashboardImg}
                    />
                ) : (
                    ''
                )}
            </Grid>
            {roles.includes('tech') && (
                <Grid
                    container
                    spacing={2}
                    marginTop={1}
                    sx={{
                        mb: 5,
                    }}
                >
                    {PGADMIN_URL && (
                        <Grid item>
                            <Link
                                href={PGADMIN_URL}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                            >
                                PgAdmin
                            </Link>
                        </Grid>
                    )}
                    {PHPMYADMIN_URL && (
                        <Grid item>
                            <Link
                                href={PHPMYADMIN_URL}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                            >
                                PhpMyAdmin
                            </Link>
                        </Grid>
                    )}
                    {ELASTICHQ_URL && (
                        <Grid item>
                            <Link
                                href={ELASTICHQ_URL}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                            >
                                ElasticHQ
                            </Link>
                        </Grid>
                    )}
                    {MAILHOG_URL && (
                        <Grid item>
                            <Link
                                href={MAILHOG_URL}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                            >
                                MailHog
                            </Link>
                        </Grid>
                    )}
                    {MATOMO_URL && (
                        <Grid item>
                            <Link
                                href={MATOMO_URL}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                            >
                                Matomo
                            </Link>
                        </Grid>
                    )}
                    {RABBITMQ_CONSOLE_URL && (
                        <Grid item>
                            <Link
                                href={RABBITMQ_CONSOLE_URL}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                            >
                                RabbitMQ
                            </Link>
                        </Grid>
                    )}
                    {TRAEFIK_CONSOLE_URL && (
                        <Grid item>
                            <Link
                                href={TRAEFIK_CONSOLE_URL}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                            >
                                Traefik Console
                            </Link>
                        </Grid>
                    )}
                    {SOKETI_USAGE_URL && (
                        <Grid item>
                            <Link
                                href={SOKETI_USAGE_URL}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                            >
                                Soketi Usage
                            </Link>
                        </Grid>
                    )}
                    {NOVU_DASHBOARD_URL && (
                        <Grid item>
                            <Link
                                href={NOVU_DASHBOARD_URL}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                            >
                                Novu Dashboard
                            </Link>
                        </Grid>
                    )}
                </Grid>
            )}
        </Container>
    );
}

function inIframe(): boolean {
    try {
        return window.self !== window.top;
    } catch (e) {
        return true;
    }
}
