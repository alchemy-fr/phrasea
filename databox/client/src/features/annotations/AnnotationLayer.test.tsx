import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {cleanup, fireEvent, render} from '@testing-library/react';
import {AnnotationLayer} from './AnnotationLayer';
import type {AnnotationEditor} from './useAnnotationEditor';
import {AnnotationType, type AssetAnnotation} from './types';
import {faceStyle} from './shapes';

const faces: AssetAnnotation[] = [
    {id: 'a', type: AnnotationType.Face, x: 0.1, y: 0.1, w: 0.2, h: 0.2},
    {id: 'b', type: AnnotationType.Face, x: 0.5, y: 0.5, w: 0.2, h: 0.2},
];

function renderLayer(editable: boolean) {
    const editor = {
        annotations: faces,
        pageAnnotations: faces,
        editable,
        active: editable,
        media: {width: 1000, height: 500},
        setMedia: vi.fn(),
        tool: undefined,
        options: {color: '#ffffff', size: 1},
        selectedId: undefined,
        selected: undefined,
        select: vi.fn(),
        draft: undefined,
        setDraft: vi.fn(),
        textEdit: undefined,
    } as unknown as AnnotationEditor;

    return render(<AnnotationLayer editor={editor} zoom={1} />).container;
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
});
