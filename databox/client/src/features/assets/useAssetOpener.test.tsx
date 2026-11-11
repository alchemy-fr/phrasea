import type {ReactNode} from 'react';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {cleanup, renderHook} from '@testing-library/react';
import type {Asset} from '@/types/api';
import {useAssetOpener} from './useAssetOpener';
import {useNavigationContextStore} from './navigationContext';
import {AssetViewerNavigationContext} from './view/AssetViewerNavigation';

const push = vi.fn();
vi.mock('next/navigation', () => ({useRouter: () => ({push})}));

const asset = {id: 'a2'} as Asset;

describe('useAssetOpener', () => {
    beforeEach(() => {
        push.mockReset();
        useNavigationContextStore.setState({context: undefined});
        window.history.replaceState(null, '', '/');
    });
    afterEach(cleanup);

    it('navigates to the viewer, with the list it was opened from', () => {
        const {result} = renderHook(() => useAssetOpener());

        result.current(asset, {siblings: ['a1', 'a2']});

        expect(push).toHaveBeenCalledWith('/assets/a2/_');
        expect(useNavigationContextStore.getState().context?.ids).toEqual([
            'a1',
            'a2',
        ]);
    });

    it('does not open the asset already open again', () => {
        window.history.replaceState(null, '', '/assets/a2/_');
        const {result} = renderHook(() => useAssetOpener());

        result.current(asset);

        expect(push).not.toHaveBeenCalled();
    });

    it('shows the asset in place from inside the viewer', () => {
        const goTo = vi.fn();
        const {result} = renderHook(() => useAssetOpener(), {
            wrapper: ({children}: {children: ReactNode}) => (
                <AssetViewerNavigationContext.Provider value={{goTo}}>
                    {children}
                </AssetViewerNavigationContext.Provider>
            ),
        });

        result.current(asset, {siblings: ['a2', 'a3'], renditionId: 'r1'});

        expect(goTo).toHaveBeenCalledWith('a2', {renditionId: 'r1'});
        expect(push).not.toHaveBeenCalled();
        expect(useNavigationContextStore.getState().context?.ids).toEqual([
            'a2',
            'a3',
        ]);
    });
});
