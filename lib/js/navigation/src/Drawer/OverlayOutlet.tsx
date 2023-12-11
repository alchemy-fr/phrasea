import React, {useRef} from 'react';
import {useLocation, useNavigate} from "react-router-dom";
import {getOverlayContext, TOverlayRouterContext} from "./OverlayRouterContext";
import OverlayRouterProvider from "./OverlayRouterProvider";


type OverlayComponentProps = {
    open: boolean;
    onClose: () => void;
};

export type OverlayComponent = (props: OverlayComponentProps) => React.ReactNode

type Props = {
    queryParam: string;
    WrapperComponent: OverlayComponent;
};

export default function OverlayOutlet({
    queryParam,
    WrapperComponent,
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

    const contextValue = React.useMemo<TOverlayRouterContext>(() => {
        return {
            close: () => {
                setOpen(false);
                onClose();
            },
        }
    }, [onClose, setOpen]);

    const OverlayRouterContext = getOverlayContext(queryParam);

    return <OverlayRouterContext.Provider value={contextValue}>
        <WrapperComponent
            open={open}
            onClose={onClose}
        >
            {finalUrl ? <OverlayRouterProvider
                path={finalUrl}
                queryParam={queryParam}
            /> : ''}
        </WrapperComponent>
    </OverlayRouterContext.Provider>
}
