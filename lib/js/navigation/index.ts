import {
    getPath,
    compileRoutes
} from './src/Router';
import {getCurrentPath, getFullPath} from "./src/utils";
import RouterProvider from "./src/RouterProvider";
import ModalStack, {StackedModalProps, useModals} from "./src/useModalStack";
import MatomoRouteProxy from "./src/proxy/MatomoRouteProxy";
import {useInRouterDirtyFormPrompt, useOutsideRouterDirtyFormPrompt} from "./src/useNavigationPrompt";
import {NavigateToOverlayFunction, useNavigateToOverlay} from "./src/useNavigateToOverlay";
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
    ModalStack,
}
export type {
    StackedModalProps,
    NavigateToOverlayFunction
};
