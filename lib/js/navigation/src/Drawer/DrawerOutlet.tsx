import React, {useRef} from 'react';
import {useLocation, useNavigate} from "react-router-dom";
import {Drawer} from "@mui/material";
import DrawerRouterProvider from "./DrawerRouterProvider";
import DrawerContext, {TDrawerContext} from "../../contexts/DrawerContext";

type Props = {};

export default function DrawerOutlet({
    queryParam = '_d'
}: Props) {
    const location = useLocation();
    const timer = useRef<ReturnType<typeof setTimeout>>();

    const {
        search,
    } = location;
    const navigate = useNavigate();
    const [open, setOpen] = React.useState(false);
    const [finalUrl, setFinalUrl] = React.useState<string | undefined>();

    const drawerUrl = React.useMemo<string | undefined>(() => {
        const searchParams = new URLSearchParams(search);
        return searchParams.get(queryParam) || undefined;
    }, [search]);

    React.useEffect(() => {
        if (timer.current) {
            clearTimeout(timer.current);
        }

        if (drawerUrl) {
            setFinalUrl(drawerUrl);
        } else {
            timer.current = setTimeout(() => {
                setFinalUrl(undefined);
            }, 200);
        }
        setOpen(Boolean(drawerUrl));
    }, [drawerUrl]);

    const onClose = React.useCallback(() => {
        const searchParams = new URLSearchParams(search);
        searchParams.delete(queryParam);

        navigate({
            pathname: location.pathname,
            search: searchParams.toString(),
        });
    }, [navigate, location]);

    const contextValue = React.useMemo<TDrawerContext>(() => {
        return {
            closeDrawer: () => {
                setOpen(false);
                onClose();
            },
        }
    }, [onClose, setOpen]);

    return <DrawerContext.Provider value={contextValue}>
        <Drawer
            anchor={'right'}
            open={open}
            onClose={onClose}
            PaperProps={{
                sx: (theme) => ({
                    width: "100%",
                    [theme.breakpoints.up('md')]: {
                        width: "80%"
                    },
                    [theme.breakpoints.up('lg')]: {
                        width: "40%"
                    },
                })
            }}
        >
            {finalUrl ? <DrawerRouterProvider
                path={finalUrl}
            /> : ''}
        </Drawer>
    </DrawerContext.Provider>
}
