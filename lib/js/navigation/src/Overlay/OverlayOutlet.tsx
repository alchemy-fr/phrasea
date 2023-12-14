import React, {PropsWithChildren, ReactNode, useRef} from 'react';
import {useLocation, useNavigate} from "react-router-dom";
import OverlayRouterProvider from "./OverlayRouterProvider";
import {getOverlayContext, TOverlayContext} from "./OverlayContext";
import {Routes} from "../types";


type OverlayComponentProps = PropsWithChildren<{
    open: boolean;
    onClose: () => void;
}>;

export type OverlayComponent = (props: OverlayComponentProps) => React.ReactNode

type Props = {
    queryParam: string;
    routes: Routes;
    WrapperComponent?: OverlayComponent;
};

export default function OverlayOutlet({
    queryParam,
    routes,
    WrapperComponent = DefaultWrapperComponent,
}: Props) {
    const location = useLocation();
    const timer = useRef<ReturnType<typeof setTimeout>>();

    const {
        search,
    } = location;
    const navigate = useNavigate();
    const [open, setOpen] = React.useState(false);
    const [finalUrl, setFinalUrl] = React.useState<string | undefined>();

    const overlayUrl = React.useMemo<string | undefined>(() => {
        const searchParams = new URLSearchParams(search);
        return searchParams.get(queryParam) || undefined;
    }, [search]);

    React.useEffect(() => {
        if (timer.current) {
            clearTimeout(timer.current);
        }

        if (overlayUrl) {
            setFinalUrl(overlayUrl);
        } else {
            timer.current = setTimeout(() => {
                setFinalUrl(undefined);
            }, 200);
        }
        setOpen(Boolean(overlayUrl));
    }, [overlayUrl]);

    const onClose = React.useCallback(() => {
        const searchParams = new URLSearchParams(search);
        searchParams.delete(queryParam);

        navigate({
            pathname: location.pathname,
            search: searchParams.toString(),
        });
    }, [navigate, location]);

    const contextValue = React.useMemo<TOverlayContext>(() => {
        return {
            close: () => {
                setOpen(false);
                onClose();
            },
        }
    }, [onClose, setOpen]);

    const OverlayContext = getOverlayContext(queryParam);

    return <OverlayContext.Provider value={contextValue}>
        <WrapperComponent
            open={open}
            onClose={onClose}
        >
            {finalUrl ? <OverlayRouterProvider
                path={finalUrl}
                queryParam={queryParam}
                routes={routes}
            /> : ''}
        </WrapperComponent>
    </OverlayContext.Provider>
}

function DefaultWrapperComponent({children}: OverlayComponentProps) {
    return children as ReactNode;
}
