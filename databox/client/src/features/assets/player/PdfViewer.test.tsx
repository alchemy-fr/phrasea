import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {useEffect, type ReactNode} from 'react';
import {
    act,
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {
    AnnotationsProvider,
    useAnnotations,
    type AnnotationTarget,
} from '@/features/annotations/AnnotationsProvider';
import {
    AnnotationType,
    type AssetAnnotation,
} from '@/features/annotations/types';
import {PdfViewer} from './PdfViewer';

vi.mock('react-i18next', () => ({
    useTranslation: () => ({
        t: (
            _key: string,
            defaultValue: string,
            options?: Record<string, unknown>
        ) =>
            defaultValue.replace(/{{(\w+)}}/g, (_m, name) =>
                String(options?.[name])
            ),
    }),
}));

/** Five pages of 600 x 800 points */
vi.mock('react-pdf', async () => {
    const {useEffect} = await import('react');
    const page = {
        getViewport: ({scale}: {scale: number}) => ({
            width: 600 * scale,
            height: 800 * scale,
        }),
    };

    return {
        pdfjs: {GlobalWorkerOptions: {}},
        Document: ({
            children,
            onLoadSuccess,
        }: {
            children?: ReactNode;
            onLoadSuccess?: (pdf: unknown) => void;
        }) => {
            useEffect(() => {
                onLoadSuccess?.({
                    numPages: 5,
                    getPage: async () => page,
                });
                // eslint-disable-next-line react-hooks/exhaustive-deps
            }, []);

            return <div>{children}</div>;
        },
        Page: ({pageNumber}: {pageNumber: number}) => (
            <div data-testid="pdf-page" data-page={pageNumber} />
        ),
    };
});

// The viewport: fitted to its width, a page is 1024 pixels high
// ((800 - 2 * 16) / 600 = 1.28), the pages start at 16, 1052, 2088, 3124…
const viewportWidth = 800;
const viewportHeight = 600;
const pageTop = (n: number) => 16 + (n - 1) * (1024 + 12);

function SetTarget({target}: {target?: AnnotationTarget}) {
    const ctx = useAnnotations();
    const setTarget = ctx?.setTarget;
    useEffect(() => {
        if (target) {
            setTarget?.(target);
        }
    }, [setTarget, target]);

    return null;
}

function renderViewer({fallback}: {fallback?: AnnotationTarget} = {}) {
    const ui = (t?: AnnotationTarget) => (
        <TooltipPrimitive.Provider>
            <AnnotationsProvider fallback={fallback}>
                <SetTarget target={t} />
                <PdfViewer src="https://s3/doc.pdf" annotate />
            </AnnotationsProvider>
        </TooltipPrimitive.Provider>
    );
    const result = render(ui());

    return {
        ...result,
        setTarget: (t: AnnotationTarget) => result.rerender(ui(t)),
        scroller: () => screen.getByTestId('pdf-scroll'),
        pageInput: () =>
            screen.getByTestId('pdf-page-input') as HTMLInputElement,
    };
}

const shape = (page: number, extra?: Partial<AssetAnnotation>) =>
    ({
        id: `s${page}`,
        type: AnnotationType.Rect,
        x: 0.1,
        y: 0.5,
        w: 0.2,
        h: 0.2,
        page,
        ...extra,
    }) as AssetAnnotation;

describe('PdfViewer', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                disconnect() {}
            }
        );
        Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
            configurable: true,
            get: () => viewportWidth,
        });
        Object.defineProperty(HTMLElement.prototype, 'clientHeight', {
            configurable: true,
            get: () => viewportHeight,
        });
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
        // Back to the getters of Element
        Reflect.deleteProperty(HTMLElement.prototype, 'clientWidth');
        Reflect.deleteProperty(HTMLElement.prototype, 'clientHeight');
    });

    it('shows the page out of the count and renders the pages in sight only', async () => {
        const {pageInput} = renderViewer();

        expect((await screen.findByTestId('pdf-page-count')).textContent).toBe(
            '/ 5'
        );
        expect(pageInput().value).toBe('1');
        expect(screen.getByTestId('zoom-level').textContent).toBe('128%');
        expect(
            screen.getAllByTestId('pdf-page').map(p => p.dataset.page)
        ).toEqual(['1', '2']);
    });

    it('goes to the page typed, and to the next and previous ones', async () => {
        const {pageInput, scroller} = renderViewer();
        await screen.findByTestId('pdf-page-count');

        fireEvent.change(pageInput(), {target: {value: '4'}});
        fireEvent.keyDown(pageInput(), {key: 'Enter'});

        expect(scroller().scrollTop).toBe(pageTop(4) - 6);
        expect(pageInput().value).toBe('4');
        expect(
            screen.getAllByTestId('pdf-page').map(p => p.dataset.page)
        ).toContain('4');

        fireEvent.click(screen.getByTestId('pdf-next-page'));
        expect(pageInput().value).toBe('5');
        expect(
            (screen.getByTestId('pdf-next-page') as HTMLButtonElement).disabled
        ).toBe(true);

        fireEvent.click(screen.getByTestId('pdf-previous-page'));
        expect(pageInput().value).toBe('4');

        // Beyond the last page: the last one
        fireEvent.change(pageInput(), {target: {value: '99'}});
        fireEvent.blur(pageInput());
        expect(pageInput().value).toBe('5');
    });

    it('scrolls to the shape selected from elsewhere, on its page', async () => {
        const {scroller, pageInput, setTarget} = renderViewer();
        await screen.findByTestId('pdf-page-count');

        act(() =>
            setTarget({
                key: 'message-1',
                annotations: [shape(1), shape(3)],
                selectedId: 's3',
            })
        );

        // The shape centered: its page at 2088, the shape 512 pixels below
        await waitFor(() => expect(pageInput().value).toBe('3'));
        const center = pageTop(3) + 0.6 * 1024;
        expect(scroller().scrollTop).toBeCloseTo(center - viewportHeight / 2);
        // Every page in sight has its layer
        expect(
            document.querySelector(
                '[data-pdf-page="3"] [data-testid="annotation-layer"]'
            )
        ).toBeTruthy();
    });

    it('shows the first shape of a message shown without a selection', async () => {
        const {pageInput, setTarget} = renderViewer();
        await screen.findByTestId('pdf-page-count');

        act(() =>
            setTarget({
                key: 'message-2',
                annotations: [shape(4), shape(2)],
            })
        );

        await waitFor(() => expect(pageInput().value).toBe('4'));
    });

    it('does not move for the shapes of the asset shown by default', async () => {
        const {scroller} = renderViewer({
            fallback: {key: 'asset', annotations: [shape(5)]},
        });
        await screen.findByTestId('pdf-page-count');

        expect(scroller().scrollTop).toBe(0);
    });

    it('shows the first page only, without tools, as a preview', async () => {
        render(<PdfViewer src="https://s3/doc.pdf" controls={false} />);

        await waitFor(() =>
            expect(
                screen.getAllByTestId('pdf-page').map(p => p.dataset.page)
            ).toEqual(['1'])
        );
        expect(screen.queryByTestId('pdf-page-indicator')).toBeNull();
        expect(document.querySelector('[data-zoom-toolbar]')).toBeNull();
    });
});
