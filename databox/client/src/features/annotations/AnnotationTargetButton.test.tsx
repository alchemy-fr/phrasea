import {useEffect} from 'react';
import {afterEach, describe, expect, it, vi} from 'vitest';
import {act, cleanup, render} from '@testing-library/react';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {
    AnnotationsProvider,
    ShapeEditorsScope,
    useAnnotations,
} from './AnnotationsProvider';
import {AnnotationTargetButton} from './AnnotationTargetButton';
import {AnnotationType, type AssetAnnotation} from './types';

vi.mock('react-i18next', () => ({
    useTranslation: () => ({t: (_key: string, value: string) => value}),
}));

const shapes: AssetAnnotation[] = [
    {type: AnnotationType.Rect, x: 0.1, y: 0.1, w: 0.2, h: 0.2},
    {id: 'kept', type: AnnotationType.Rect, x: 0.5, y: 0.5, w: 0.2, h: 0.2},
];

/** A shape of the asset, as the player shows it */
const shownShape = (index: number): AssetAnnotation => ({
    ...shapes[index],
    id: `attr-1-${index}`,
    source: {attributeId: 'attr-1', definitionId: 'def-1', index},
});

function setup({
    enabled = true,
    editable = true,
}: {enabled?: boolean; editable?: boolean} = {}) {
    let ctx: ReturnType<typeof useAnnotations>;
    function Spy() {
        const value = useAnnotations();
        ctx = value;
        // The player tells the media shows annotations
        useEffect(() => value?.setSupported(true), [value]);

        return null;
    }
    render(
        <TooltipPrimitive.Provider>
            <AnnotationsProvider>
                <Spy />
                <ShapeEditorsScope enabled={enabled}>
                    <AnnotationTargetButton
                        targetKey="attribute-def-1"
                        annotations={shapes}
                        editable={editable}
                        onChange={() => {}}
                        attributeId="attr-1"
                    />
                </ShapeEditorsScope>
            </AnnotationsProvider>
        </TooltipPrimitive.Provider>
    );

    return () => ctx!;
}

describe('AnnotationTargetButton shape editor', () => {
    afterEach(cleanup);

    it('opens a clicked shape of its value, selected', () => {
        const ctx = setup();

        let opened = false;
        act(() => {
            opened = ctx().editShape(shownShape(0));
        });
        expect(opened).toBe(true);
        expect(ctx().target?.key).toBe('attribute-def-1');
        expect(ctx().target?.editable).toBe(true);
        expect(ctx().target?.selectedId).toBe('attribute-def-1-0');
        // Its own shapes of the asset are left out of the ones kept in sight
        expect(ctx().target?.attributeId).toBe('attr-1');

        act(() => {
            ctx().editShape(shownShape(1));
        });
        expect(ctx().target?.selectedId).toBe('kept');
    });

    it('leaves the shapes of other values alone', () => {
        const ctx = setup();

        expect(
            ctx().editShape({
                ...shownShape(0),
                source: {
                    attributeId: 'attr-2',
                    definitionId: 'def-1',
                    index: 0,
                },
            })
        ).toBe(false);
        expect(ctx().target).toBeUndefined();
    });

    it('takes no click while the editor is hidden or read only', () => {
        expect(setup({enabled: false})().editShape(shownShape(0))).toBe(false);
        cleanup();
        expect(setup({editable: false})().editShape(shownShape(0))).toBe(false);
    });
});
