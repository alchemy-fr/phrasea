import {compileRoutes, getPath} from './src/Router';
import {getCurrentPath, getRelativeUrl, getFullPath} from './src/utils';
import RouterProvider from './src/RouterProvider';
import ModalStack, {StackedModalProps, useModals} from './src/useModalStack';
import {useFormPrompt} from './src/useNavigationPrompt';
import {
    CloseOverlayFunction,
    NavigateToOverlayFunction,
    useCloseOverlay,
    useNavigateToOverlay,
} from './src/useNavigateToOverlay';
import {
    Link,
    useLocation,
    useNavigate,
    useParams,
    useNavigation,
} from 'react-router-dom';
import OverlayOutlet from './src/Overlay/OverlayOutlet';
import {useOverlay} from './src/Overlay/OverlayContext';

export * from './src/types';
export {
    useOverlay,
    OverlayOutlet,
    getPath,
    compileRoutes,
    getRelativeUrl,
    getCurrentPath,
    getFullPath,
    useModals,
    RouterProvider,
    useFormPrompt,
    useNavigateToOverlay,
    useCloseOverlay,
    ModalStack,
    useLocation,
    useParams,
    Link,
    useNavigate,
    useNavigation,
};
export type {
    StackedModalProps,
    NavigateToOverlayFunction,
    CloseOverlayFunction,
};
