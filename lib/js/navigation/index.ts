import {
    getPath,
    compileRoutes
} from './src/Router';
import {getCurrentPath, getFullPath} from "./src/utils";
import RouterProvider from "./src/RouterProvider";
import ModalStack, {StackedModalProps, useModals} from "./src/useModalStack";
import MatomoRouteProxy from "./src/proxy/MatomoRouteProxy";
import {useInRouterDirtyFormPrompt, useOutsideRouterDirtyFormPrompt} from "./src/useNavigationPrompt";
import {NavigateToOverlayFunction, CloseOverlayFunction, useNavigateToOverlay, useCloseOverlay} from "./src/useNavigateToOverlay";
import {Link, useLocation, useParams} from "react-router-dom";
export * from "./src/types";
export {
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
}
export type {
    StackedModalProps,
    NavigateToOverlayFunction,
    CloseOverlayFunction,
};
