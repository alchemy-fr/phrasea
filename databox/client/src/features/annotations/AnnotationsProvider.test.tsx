import {afterEach, describe, expect, it} from 'vitest';
import {act, cleanup, render} from '@testing-library/react';
import {
    AnnotationsProvider,
    useAnnotations,
    type AnnotationTarget,
} from './AnnotationsProvider';
import {AnnotationType, type AssetAnnotation} from './types';

const shape = (
    id: string,
    definitionId: string,
    title: string
): AssetAnnotation => ({
    id,
    type: AnnotationType.Rect,
    title,
    source: {attributeId: id, definitionId, index: 0},
});

const fallback: AnnotationTarget = {
    key: 'asset-1',
    label: 'Attributes',
    annotations: [
        shape('p1', 'persons', 'Persons'),
        shape('l1', 'places', 'Places'),
        shape('p2', 'persons', 'Persons'),
    ],
};

function setup(f: AnnotationTarget = fallback) {
    let ctx: ReturnType<typeof useAnnotations>;
    function Spy() {
        ctx = useAnnotations();

        return null;
    }
    const view = render(
        <AnnotationsProvider fallback={f}>
            <Spy />
        </AnnotationsProvider>
    );

    return {
        ctx: () => ctx!,
        rerender: (next: AnnotationTarget) =>
            view.rerender(
                <AnnotationsProvider fallback={next}>
                    <Spy />
                </AnnotationsProvider>
            ),
    };
}

const shownIds = (target: AnnotationTarget | undefined) =>
    target?.annotations.map(a => a.id);

describe('AnnotationsProvider sets', () => {
    afterEach(cleanup);

    it('shows the shapes of the asset of one attribute, or all of them', () => {
        const {ctx} = setup();
        expect(ctx().sets.map(s => [s.id, s.count])).toEqual([
            ['persons', 2],
            ['places', 1],
        ]);
        expect(shownIds(ctx().target)).toEqual(['p1', 'l1', 'p2']);

        act(() => ctx().showSet('persons'));
        expect(ctx().setId).toBe('persons');
        expect(ctx().target?.key).toBe('asset-1');
        expect(ctx().target?.label).toBe('Persons');
        expect(shownIds(ctx().target)).toEqual(['p1', 'p2']);

        act(() => ctx().showSet(undefined));
        expect(ctx().target?.label).toBe('Attributes');
        expect(shownIds(ctx().target)).toEqual(['p1', 'l1', 'p2']);
    });

    it('leaves the edited shapes for the set chosen', () => {
        const {ctx} = setup();
        act(() =>
            ctx().setTarget({key: 'editing', annotations: [], editable: true})
        );
        expect(ctx().target?.key).toBe('editing');

        act(() => ctx().showSet('places'));
        expect(shownIds(ctx().target)).toEqual(['l1']);
    });

    it('shows them all on an asset without the attribute chosen', () => {
        const {ctx, rerender} = setup();
        act(() => ctx().showSet('places'));

        rerender({...fallback, annotations: fallback.annotations.slice(2)});
        expect(ctx().setId).toBeUndefined();
        expect(shownIds(ctx().target)).toEqual(['p2']);
    });
});
