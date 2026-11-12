import {afterEach, describe, expect, it} from 'vitest';
import {act, cleanup, renderHook} from '@testing-library/react';
import {useActiveViewer, useViewerStackStore} from './viewerStack';

describe('useActiveViewer', () => {
    afterEach(() => {
        cleanup();
        useViewerStackStore.setState({viewers: []});
    });

    it('shows a lone viewer', () => {
        const viewer = renderHook(() => useActiveViewer());

        expect(viewer.result.current).toBe(true);
    });

    it('only shows the last mounted viewer, until it unmounts', () => {
        const page = renderHook(() => useActiveViewer());
        const modal = renderHook(() => useActiveViewer());

        expect(page.result.current).toBe(false);
        expect(modal.result.current).toBe(true);

        act(() => modal.unmount());

        expect(page.result.current).toBe(true);
    });
});
