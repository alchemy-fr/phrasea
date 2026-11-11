'use client';

import 'react-pdf/dist/Page/TextLayer.css';
import 'react-pdf/dist/Page/AnnotationLayer.css';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import {Document, Page, pdfjs} from 'react-pdf';
import {
    ChevronDownIcon,
    ChevronUpIcon,
    MaximizeIcon,
    MinusIcon,
    MoveHorizontalIcon,
    PlusIcon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Tooltip} from '@alchemy/next-ui/ui/overlays';
import {Spinner} from '@alchemy/next-ui/ui/loader';
import {cn} from '@alchemy/next-ui/utils/cn';
import {clamp} from '@alchemy/next-ui/utils/misc';
import {nextZoomStep, wheelFactor} from './zoom';
import {
    canvasPixelRatio,
    currentPage,
    fitScale,
    layoutPages,
    MAX_PDF_ZOOM,
    PAGE_GAP,
    PAGE_PADDING,
    pageAt,
    pagesToRender,
    type PagesLayout,
    type PageSize,
    type PdfFit,
} from './pdfLayout';

// Served with the application: set in the module rendering the components,
// react-pdf would set its default otherwise
pdfjs.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.min.mjs',
    import.meta.url
).toString();

type Props = {
    src: string;
    /** Without, the first page only, fitted, with nothing to interact with */
    controls?: boolean;
    className?: string;
    onInteraction?: () => void;
    /** A click in the document, not on the toolbar */
    onContentClick?: () => void;
};

type Zoom = {fit: PdfFit} | {scale: number};

type Viewport = {width: number; height: number};

type ViewportPoint = {x: number; y: number};

/** A point of the content, kept under the same point of the viewport */
type Anchor = {
    page: number;
    /** Position in the page, relative to its height */
    fy: number;
    /** Position in the content, relative to its width */
    fx: number;
    at: ViewportPoint;
};

function pageSizeOf(page: {
    getViewport: (options: {scale: number}) => {width: number; height: number};
}): PageSize {
    const {width, height} = page.getViewport({scale: 1});

    return {width, height};
}

function contentWidth(layout: PagesLayout, viewport: Viewport): number {
    return Math.max(viewport.width, layout.width + 2 * PAGE_PADDING);
}

/**
 * PDF reader: the pages scrolled continuously, the ones near the viewport
 * only rendered, with the selection of their text.
 */
export function PdfViewer({controls = true, ...props}: Props) {
    return controls ? (
        <PdfDocumentViewer {...props} />
    ) : (
        <PdfFirstPage src={props.src} className={props.className} />
    );
}

function PdfDocumentViewer({
    src,
    className,
    onInteraction,
    onContentClick,
}: Omit<Props, 'controls'>) {
    const {t} = useTranslation();
    const scrollRef = useRef<HTMLDivElement>(null);
    const [numPages, setNumPages] = useState(0);
    const [sizes, setSizes] = useState<Record<number, PageSize>>({});
    const [zoom, setZoom] = useState<Zoom>({fit: 'width'});
    const [viewport, setViewport] = useState<Viewport>({width: 0, height: 0});
    const [scroll, setScroll] = useState({top: 0, left: 0});
    const [pageInput, setPageInput] = useState<string>();

    const first = sizes[1];
    const fitPageScale =
        first && viewport.width ? fitScale(first, viewport, 'page') : 1;
    const scale =
        !first || !viewport.width
            ? 1
            : 'fit' in zoom
              ? fitScale(first, viewport, zoom.fit)
              : zoom.scale;
    const layout = useMemo(
        () => (first ? layoutPages(numPages, sizes, first, scale) : undefined),
        [numPages, sizes, first, scale]
    );
    const ready = !!layout && numPages > 0 && viewport.width > 0;
    const page = layout ? currentPage(layout, scroll.top, viewport.height) : 1;
    const rendered = useMemo(
        () =>
            new Set(
                layout && viewport.width
                    ? pagesToRender(layout, scroll.top, viewport.height)
                    : []
            ),
        [layout, scroll.top, viewport]
    );

    const setPageSize = useCallback((n: number, size: PageSize) => {
        setSizes(prev => {
            const current = prev[n];

            return current &&
                current.width === size.width &&
                current.height === size.height
                ? prev
                : {...prev, [n]: size};
        });
    }, []);

    const readScroll = () => {
        const el = scrollRef.current;
        if (el) {
            setScroll({top: el.scrollTop, left: el.scrollLeft});
        }
    };
    const scrollTo = (top: number, left?: number) => {
        const el = scrollRef.current;
        if (!el) {
            return;
        }
        el.scrollTop = Math.max(0, top);
        if (left !== undefined) {
            el.scrollLeft = Math.max(0, left);
        }
        readScroll();
    };

    useLayoutEffect(() => {
        const el = scrollRef.current!;
        const measure = () =>
            setViewport(v =>
                v.width === el.clientWidth && v.height === el.clientHeight
                    ? v
                    : {width: el.clientWidth, height: el.clientHeight}
            );
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    // The point of the content under the anchor stays there when the pages
    // change size (zoom, fit to a resized viewport): the top of the viewport
    // unless the zoom says otherwise
    const anchorRef = useRef<Anchor>(undefined);
    const prevLayoutRef = useRef<PagesLayout>(undefined);
    const captureAnchor = (
        from: PagesLayout,
        at: ViewportPoint
    ): Anchor | undefined => {
        const el = scrollRef.current;
        if (!el) {
            return;
        }
        const y = el.scrollTop + at.y;
        const n = pageAt(from, y);

        return {
            page: n,
            fy: (y - from.tops[n - 1]) / (from.heights[n - 1] || 1),
            fx: (el.scrollLeft + at.x) / contentWidth(from, viewport),
            at,
        };
    };
    useLayoutEffect(() => {
        const prev = prevLayoutRef.current;
        prevLayoutRef.current = layout;
        if (!layout || !prev || prev === layout) {
            return;
        }
        const anchor = anchorRef.current ?? captureAnchor(prev, {x: 0, y: 0});
        anchorRef.current = undefined;
        if (!anchor || !layout.tops[anchor.page - 1]) {
            return;
        }
        const i = anchor.page - 1;
        scrollTo(
            layout.tops[i] + anchor.fy * layout.heights[i] - anchor.at.y,
            anchor.fx * contentWidth(layout, viewport) - anchor.at.x
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [layout]);

    const setScale = (next: number, at?: ViewportPoint) => {
        const el = scrollRef.current;
        if (!el || !layout) {
            return;
        }
        const clamped = clamp(next, fitPageScale, MAX_PDF_ZOOM);
        anchorRef.current = captureAnchor(
            layout,
            at ?? {x: el.clientWidth / 2, y: el.clientHeight / 2}
        );
        setZoom({scale: clamped});
        onInteraction?.();
    };
    const setFit = (fit: PdfFit) => {
        setZoom({fit});
        onInteraction?.();
    };
    const zoomStep = (direction: 1 | -1) =>
        nextZoomStep(scale, fitPageScale, direction);
    const step = (direction: 1 | -1) => {
        const next = zoomStep(direction);
        if (next !== undefined) {
            setScale(next);
        }
    };

    const goTo = (n: number) => {
        if (!layout || numPages === 0) {
            return;
        }
        const target = clamp(Math.round(n), 1, numPages);
        scrollTo(target === 1 ? 0 : layout.tops[target - 1] - PAGE_GAP / 2);
        onInteraction?.();
    };
    const commitPageInput = () => {
        const n = Number.parseInt(pageInput ?? '', 10);
        setPageInput(undefined);
        if (Number.isFinite(n)) {
            goTo(n);
        }
    };

    // Not through React: its wheel listeners are passive, the browser would
    // zoom the whole page on a ctrl + wheel (or a pinch)
    const onWheelRef = useRef<(e: WheelEvent) => void>(undefined);
    onWheelRef.current = e => {
        if (!e.ctrlKey && !e.metaKey) {
            return;
        }
        e.preventDefault();
        const rect = scrollRef.current!.getBoundingClientRect();
        setScale(scale * wheelFactor(e), {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top,
        });
    };
    useEffect(() => {
        const el = scrollRef.current!;
        const listener = (e: WheelEvent) => onWheelRef.current?.(e);
        el.addEventListener('wheel', listener, {passive: false});

        return () => el.removeEventListener('wheel', listener);
    }, []);

    const screenRatio =
        typeof window !== 'undefined' ? window.devicePixelRatio || 1 : 1;
    const fit = 'fit' in zoom ? zoom.fit : undefined;
    const width = layout ? contentWidth(layout, viewport) : 0;

    return (
        <div
            data-testid="pdf-viewer"
            className={cn('relative size-full overflow-hidden', className)}
        >
            <div
                ref={scrollRef}
                data-testid="pdf-scroll"
                className="absolute inset-0 isolate overflow-auto [scrollbar-gutter:stable]"
                onScroll={readScroll}
                onClick={() => {
                    onInteraction?.();
                    onContentClick?.();
                }}
            >
                <Document
                    file={src}
                    onLoadSuccess={pdf => {
                        setNumPages(pdf.numPages);
                        pdf.getPage(1).then(
                            p => setPageSize(1, pageSizeOf(p)),
                            () => undefined
                        );
                    }}
                    loading={
                        <div className="flex size-full items-center justify-center">
                            <Spinner className="size-8" />
                        </div>
                    }
                    error={
                        <div className="flex size-full items-center justify-center p-4 text-sm text-muted-foreground">
                            {t(
                                'player.pdf_error',
                                'The document cannot be displayed'
                            )}
                        </div>
                    }
                    className="contents"
                >
                    {layout ? (
                        <div
                            className="relative"
                            style={{width, height: layout.height}}
                        >
                            {layout.tops.map((top, i) => {
                                const n = i + 1;
                                const w = layout.widths[i];
                                const h = layout.heights[i];

                                return (
                                    <div
                                        key={n}
                                        data-pdf-page={n}
                                        className="absolute bg-white shadow-md"
                                        style={{
                                            top,
                                            left: (width - w) / 2,
                                            width: w,
                                            height: h,
                                        }}
                                    >
                                        {rendered.has(n) ? (
                                            <Page
                                                pageNumber={n}
                                                scale={scale}
                                                devicePixelRatio={canvasPixelRatio(
                                                    w,
                                                    h,
                                                    screenRatio
                                                )}
                                                loading={null}
                                                onLoadSuccess={p =>
                                                    setPageSize(
                                                        n,
                                                        pageSizeOf(p)
                                                    )
                                                }
                                            />
                                        ) : null}
                                    </div>
                                );
                            })}
                        </div>
                    ) : null}
                </Document>
            </div>
            {ready ? (
                <div
                    data-zoom-toolbar
                    className="absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full border bg-background/90 p-1 shadow-md"
                >
                    <Tooltip
                        content={t('player.previous_page', 'Previous page')}
                    >
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            data-testid="pdf-previous-page"
                            onClick={() => goTo(page - 1)}
                            disabled={page <= 1}
                            aria-label={t(
                                'player.previous_page',
                                'Previous page'
                            )}
                        >
                            <ChevronUpIcon />
                        </Button>
                    </Tooltip>
                    <div
                        data-testid="pdf-page-indicator"
                        className="flex items-center gap-1 font-mono text-xs"
                    >
                        <input
                            data-testid="pdf-page-input"
                            aria-label={t('player.page', 'Page')}
                            inputMode="numeric"
                            className="h-6 w-10 rounded-md border bg-transparent text-center outline-none focus-visible:ring-2 focus-visible:ring-ring/60"
                            value={pageInput ?? String(page)}
                            onChange={e => setPageInput(e.target.value)}
                            onFocus={e => e.currentTarget.select()}
                            onBlur={commitPageInput}
                            onKeyDown={e => {
                                if (e.key === 'Enter') {
                                    commitPageInput();
                                    e.currentTarget.blur();
                                } else if (e.key === 'Escape') {
                                    setPageInput(undefined);
                                    e.currentTarget.blur();
                                }
                            }}
                        />
                        <span
                            data-testid="pdf-page-count"
                            className="pr-1 text-muted-foreground"
                        >
                            / {numPages}
                        </span>
                    </div>
                    <Tooltip content={t('player.next_page', 'Next page')}>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            data-testid="pdf-next-page"
                            onClick={() => goTo(page + 1)}
                            disabled={page >= numPages}
                            aria-label={t('player.next_page', 'Next page')}
                        >
                            <ChevronDownIcon />
                        </Button>
                    </Tooltip>
                    <span className="mx-0.5 h-4 w-px bg-border" />
                    <Tooltip content={t('player.zoom_out', 'Zoom out')}>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            onClick={() => step(-1)}
                            disabled={zoomStep(-1) === undefined}
                            aria-label={t('player.zoom_out', 'Zoom out')}
                        >
                            <MinusIcon />
                        </Button>
                    </Tooltip>
                    <Tooltip content={t('player.actual_size', 'Actual size')}>
                        <button
                            type="button"
                            data-testid="zoom-level"
                            className="h-6 w-12 rounded-full text-center font-mono text-xs hover:bg-accent"
                            onClick={() => setScale(1)}
                        >
                            {`${Math.round(scale * 100)}%`}
                        </button>
                    </Tooltip>
                    <Tooltip content={t('player.zoom_in', 'Zoom in')}>
                        <Button
                            data-testid="pdf-zoom-in"
                            variant="ghost"
                            size="icon-xs"
                            onClick={() => step(1)}
                            disabled={
                                scale >= MAX_PDF_ZOOM ||
                                zoomStep(1) === undefined
                            }
                            aria-label={t('player.zoom_in', 'Zoom in')}
                        >
                            <PlusIcon />
                        </Button>
                    </Tooltip>
                    <Tooltip content={t('player.fit_width', 'Fit width')}>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            data-testid="pdf-fit-width"
                            aria-pressed={fit === 'width'}
                            onClick={() => setFit('width')}
                            aria-label={t('player.fit_width', 'Fit width')}
                        >
                            <MoveHorizontalIcon />
                        </Button>
                    </Tooltip>
                    <Tooltip content={t('player.fit_page', 'Fit page')}>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            data-testid="pdf-fit-page"
                            aria-pressed={fit === 'page'}
                            onClick={() => setFit('page')}
                            aria-label={t('player.fit_page', 'Fit page')}
                        >
                            <MaximizeIcon />
                        </Button>
                    </Tooltip>
                </div>
            ) : null}
        </div>
    );
}

/** The first page, fitted to the box: a thumbnail, a preview */
function PdfFirstPage({src, className}: {src: string; className?: string}) {
    const {t} = useTranslation();
    const ref = useRef<HTMLDivElement>(null);
    const [box, setBox] = useState<Viewport>({width: 0, height: 0});
    const [size, setSize] = useState<PageSize>();

    useLayoutEffect(() => {
        const el = ref.current!;
        const measure = () =>
            setBox(b =>
                b.width === el.clientWidth && b.height === el.clientHeight
                    ? b
                    : {width: el.clientWidth, height: el.clientHeight}
            );
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    const scale =
        size && box.width && box.height
            ? Math.min(box.width / size.width, box.height / size.height)
            : undefined;

    return (
        <div
            ref={ref}
            data-testid="pdf-preview"
            className={cn(
                'flex size-full items-center justify-center overflow-hidden',
                className
            )}
        >
            <Document
                file={src}
                onLoadSuccess={pdf =>
                    pdf.getPage(1).then(
                        p => setSize(pageSizeOf(p)),
                        () => undefined
                    )
                }
                loading={<Spinner />}
                error={
                    <span className="p-2 text-center text-xs text-muted-foreground">
                        {t(
                            'player.pdf_error',
                            'The document cannot be displayed'
                        )}
                    </span>
                }
                className="contents"
            >
                {scale ? (
                    <Page
                        pageNumber={1}
                        scale={scale}
                        renderTextLayer={false}
                        renderAnnotationLayer={false}
                        loading={null}
                        className="shadow-md"
                    />
                ) : null}
            </Document>
        </div>
    );
}
