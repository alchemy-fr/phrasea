import AppBar from '@mui/material/AppBar';
import Toolbar from '@mui/material/Toolbar';
import {PropsWithChildren} from "react";
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import config from "./config.ts";
import {keycloakClient} from "./lib/apiClient.ts";
import MenuItem from "@mui/material/MenuItem";
import Box from "@mui/material/Box";
import {UserMenu} from '@alchemy/phrasea-ui';
import {useTranslation} from "react-i18next";

type Props = PropsWithChildren<{}>;

export default function DashboardBar({
    children
}: Props) {
    const menuHeight = 42;
    const {t} = useTranslation();
    const {getLoginUrl, getAccountUrl} = useKeycloakUrls({
        autoConnectIdP: config.autoConnectIdP,
        keycloakClient,
    });

    const {user, logout} = useAuth();

    return (
        <AppBar position="sticky">
            <Toolbar>
                <div style={{
                    flexGrow: 1
                }}>
                    {children}
                </div>


                <Box sx={{flexGrow: 0}}>
                    {!user ? (
                        <MenuItem component={'a'} href={getLoginUrl()}>
                            {t('menu.sign_in', 'Sign in')}
                        </MenuItem>
                    ) : (
                        <UserMenu
                            menuHeight={menuHeight}
                            username={user?.username}
                            accountUrl={getAccountUrl()}
                            onLogout={logout}
                        />
                    )}
                </Box>
            </Toolbar>
        </AppBar>
    );
}
