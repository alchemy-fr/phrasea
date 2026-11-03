import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import type {ReactNode} from 'react';
import {cleanup, fireEvent, render} from '@testing-library/react';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {AnnotationLayer} from './AnnotationLayer';
import type {AnnotationEditor} from './useAnnotationEditor';
import {AnnotationType, type AssetAnnotation} from './types';
import {faceStyle} from './shapes';

const faces: AssetAnnotation[] = [
    {id: 'a', type: AnnotationType.Face, x: 0.1, y: 0.1, w: 0.2, h: 0.2},
    {id: 'b', type: AnnotationType.Face, x: 0.5, y: 0.5, w: 0.2, h: 0.2},
];

function renderLayer(
    editable: boolean,
    {
        selectedId,
        renderControl,
        onEditShape,
        select = vi.fn(),
        context,
        canEditShape,
        active = editable,
        setAnnotate = vi.fn(),
    }: {
        selectedId?: string;
        renderControl?: (annotation: AssetAnnotation) => ReactNode;
        onEditShape?: (annotation: AssetAnnotation) => boolean;
        select?: (id: string | undefined) => void;
        context?: AssetAnnotation[];
        canEditShape?: (annotation: AssetAnnotation) => boolean;
        active?: boolean;
        setAnnotate?: (annotate: boolean) => void;
    } = {}
) {
    const editor = {
        annotations: faces,
        pageAnnotations: faces,
        editable,
        active,
        setAnnotate,
        media: {width: 1000, height: 500},
        setMedia: vi.fn(),
        tool: undefined,
        options: {color: '#ffffff', size: 1},
        selectedId,
        selected: faces.find(f => f.id === selectedId),
        select,
        draft: undefined,
        setDraft: vi.fn(),
        textEdit: undefined,
    } as unknown as AnnotationEditor;

    return render(
        <TooltipPrimitive.Provider>
            <AnnotationLayer
                editor={editor}
                zoom={1}
                renderControl={renderControl}
                onEditShape={onEditShape}
                context={context}
                canEditShape={canEditShape}
            />
        </TooltipPrimitive.Provider>
    ).container;
}

const veil = (container: HTMLElement, id: string) =>
    container.querySelector(`[data-annotation-mask="${id}"]`) as SVGGElement;

const ovalGroup = (container: HTMLElement, id: string) =>
    container.querySelector(`[data-annotation-id="${id}"] > g`) as SVGGElement;

describe('AnnotationLayer veils', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                disconnect() {}
            }
        );
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('fades in the veil of the hovered face and grows it, read only', () => {
        const container = renderLayer(false);
        expect(veil(container, 'a').style.opacity).toBe('0');
        expect(veil(container, 'b').style.opacity).toBe('0');

        fireEvent.pointerEnter(
            container.querySelector('[data-annotation-id="a"]')!
        );

        expect(veil(container, 'a').getAttribute('data-visible')).toBe('true');
        expect(veil(container, 'a').style.opacity).toBe('1');
        expect(veil(container, 'b').getAttribute('data-visible')).toBeNull();
        expect(veil(container, 'b').style.opacity).toBe('0');
        expect(ovalGroup(container, 'a').style.transform).toBe(
            `scale(${faceStyle.expand})`
        );
        expect(ovalGroup(container, 'b').style.transform).toBe('none');
        const maskIds = Array.from(container.querySelectorAll('mask')).map(
            m => m.id
        );
        expect(new Set(maskIds).size).toBe(2);
        for (const id of maskIds) {
            expect(id).toMatch(/^[a-zA-Z0-9_-]+$/);
        }
    });

    it('keeps the oval to its size when editable', () => {
        const container = renderLayer(true);
        fireEvent.pointerEnter(
            container.querySelector('[data-annotation-id="a"]')!
        );

        expect(veil(container, 'a').getAttribute('data-visible')).toBe('true');
        expect(ovalGroup(container, 'a').style.transform).toBe('none');
    });

    it('shows the control of the selected shape instead of its name, read only', () => {
        const renderControl = (annotation: AssetAnnotation) =>
            annotation.id === 'a' ? <button>Who is this?</button> : null;

        const withControl = renderLayer(false, {
            selectedId: 'a',
            renderControl,
        });
        expect(withControl.querySelector('[data-shape-name]')).toBeNull();
        expect(withControl.textContent).toContain('Who is this?');
        cleanup();

        const withoutControl = renderLayer(false, {
            selectedId: 'b',
            renderControl,
        });
        expect(withoutControl.querySelector('[data-shape-name]')).toBeTruthy();
        expect(withoutControl.textContent).not.toContain('Who is this?');
    });

    it('opens a clicked shape where it is edited, else selects it', () => {
        const select = vi.fn();
        const onEditShape = vi.fn((a: AssetAnnotation) => a.id === 'a');
        const container = renderLayer(false, {onEditShape, select});
        const press = (id: string) =>
            fireEvent.pointerDown(
                container.querySelector(`[data-annotation-id="${id}"] *`)!,
                {button: 0}
            );

        press('a');
        expect(onEditShape).toHaveBeenLastCalledWith(faces[0]);
        expect(select).not.toHaveBeenCalled();

        press('b');
        expect(onEditShape).toHaveBeenLastCalledWith(faces[1]);
        expect(select).toHaveBeenCalledWith('b');
    });

    it('shows the other shapes faded, the ones with an editor clickable', () => {
        const other = (id: string): AssetAnnotation => ({
            id,
            type: AnnotationType.Rect,
            x: 0.7,
            y: 0.7,
            w: 0.1,
            h: 0.1,
        });
        const onEditShape = vi.fn(() => true);
        const select = vi.fn();
        const container = renderLayer(true, {
            context: [other('editable'), other('fixed')],
            canEditShape: a => a.id === 'editable',
            onEditShape,
            select,
        });
        const shape = (id: string) =>
            container.querySelector(
                `[data-annotation-context-id="${id}"]`
            ) as SVGGElement;

        expect(shape('editable').getAttribute('class')).toContain('opacity-50');
        expect(shape('editable').style.pointerEvents.toLowerCase()).toBe(
            'visiblepainted'
        );
        expect(shape('fixed').style.pointerEvents).toBe('none');
        expect(
            container.querySelector('[data-annotation-id="editable"]')
        ).toBeNull();

        fireEvent.pointerDown(shape('editable').firstElementChild!, {
            button: 0,
        });
        expect(onEditShape).toHaveBeenCalledWith(other('editable'));
        expect(select).not.toHaveBeenCalled();
    });

    it('takes the tools back with the shape pressed while paused', () => {
        const select = vi.fn();
        const setAnnotate = vi.fn();
        const container = renderLayer(true, {
            active: false,
            select,
            setAnnotate,
        });
        const shape = container.querySelector(
            '[data-annotation-id="a"]'
        ) as SVGGElement;
        expect(shape.style.pointerEvents.toLowerCase()).toBe('visiblepainted');

        fireEvent.pointerDown(shape.firstElementChild!, {button: 0});
        expect(setAnnotate).toHaveBeenCalledWith(true);
        expect(select).toHaveBeenCalledWith('a');
    });

    it('goes from the selected shape to the one under it, of another value', () => {
        const below: AssetAnnotation = {
            id: 'below',
            type: AnnotationType.Face,
            x: 0.1,
            y: 0.1,
            w: 0.2,
            h: 0.2,
        };
        const onEditShape = vi.fn(() => true);
        const select = vi.fn();
        const container = renderLayer(true, {
            selectedId: 'a',
            context: [below],
            canEditShape: () => true,
            onEditShape,
            select,
        });
        const svg = container.querySelector('svg')!;
        // jsdom has neither
        svg.setPointerCapture = () => {};
        svg.getScreenCTM = () => null;
        const top = container.querySelector(
            '[data-annotation-id="a"] ellipse'
        )!;
        const under = container.querySelector(
            '[data-annotation-context-id="below"] ellipse'
        )!;
        document.elementsFromPoint = () => [top, under];

        fireEvent.pointerDown(top, {button: 0, pointerId: 1});
        fireEvent.pointerUp(top, {button: 0, pointerId: 1});

        expect(onEditShape).toHaveBeenCalledWith(below);
        expect(select).not.toHaveBeenCalled();
    });
});
