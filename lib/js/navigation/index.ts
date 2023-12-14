import {compileRoutes, getPath} from './src/Router';
import {getCurrentPath, getFullPath} from "./src/utils";
import RouterProvider from "./src/RouterProvider";
import ModalStack, {StackedModalProps, useModals} from "./src/useModalStack";
import MatomoRouteProxy from "./src/proxy/MatomoRouteProxy";
import {useInRouterDirtyFormPrompt, useOutsideRouterDirtyFormPrompt} from "./src/useNavigationPrompt";
import {
    CloseOverlayFunction,
    NavigateToOverlayFunction,
    useCloseOverlay,
    useNavigateToOverlay
} from "./src/useNavigateToOverlay";
import {Link, useLocation, useNavigate, useParams} from "react-router-dom";
import OverlayOutlet from "./src/Overlay/OverlayOutlet";
import {useOverlay} from "./src/Overlay/OverlayContext";

export * from "./src/types";
export {
    useOverlay,
    OverlayOutlet,
    getPath,
    compileRoutes,
    getCurrentPath,
    getFullPath,
    useModals,
    MatomoRouteProxy,
    RouterProvider,
    useInRouterDirtyFormPrompt,
    useOutsideRouterDirtyFormPrompt,
    useNavigateToOverlay,
    useCloseOverlay,
    ModalStack,
    useLocation,
    useParams,
    Link,
    useNavigate,
}
export type {
    StackedModalProps,
    NavigateToOverlayFunction,
    CloseOverlayFunction,
};
