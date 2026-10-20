import {describe, expect, it, vi} from 'vitest';
import {act, renderHook} from '@testing-library/react';
import type {AnnotationTarget} from './AnnotationsProvider';
import {useAnnotationEditor} from './useAnnotationEditor';
import {
    AnnotationType,
    type AssetAnnotation,
    type TextAnnotation,
} from './types';

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

const media = {width: 1000, height: 500};

/** A target whose list follows the edits, like a component owning it */
function setup(initial: AssetAnnotation[] = [], editable = true) {
    const onChange = vi.fn();
    let target: AnnotationTarget = {
        key: 'k',
        annotations: initial,
        editable,
        onChange,
    };
    const hook = renderHook(
        ({target}: {target: AnnotationTarget}) =>
            useAnnotationEditor(target, undefined),
        {initialProps: {target}}
    );
    act(() => hook.result.current.setMedia(media));
    const sync = () => {
        const next = onChange.mock.lastCall?.[0];
        if (next) {
            target = {...target, annotations: next};
            hook.rerender({target});
        }
    };

    return {hook, onChange, sync, editor: () => hook.result.current};
}

const rect: AssetAnnotation = {
    id: 'r1',
    type: AnnotationType.Rect,
    x: 0.1,
    y: 0.1,
    w: 0.2,
    h: 0.2,
    c: '#000000',
    s: 0.002,
};

describe('useAnnotationEditor', () => {
    it('adds a named shape, selects it and sends the list', () => {
        const {editor, onChange, sync} = setup();
        let created: AssetAnnotation | undefined;
        act(() => {
            created = editor().add({
                type: AnnotationType.Circle,
                x: 0.5,
                y: 0.5,
                r: 0.1,
            });
        });
        expect(created?.id).toMatch(/^annotation-/);
        expect(created?.name).toBe('Circle #1');
        expect(onChange).toHaveBeenCalledWith([created]);
        sync();
        expect(editor().selectedId).toBe(created!.id);
        expect(editor().selected).toEqual(created);
    });

    it('gives the shapes of integrations an id and keeps it once edited', () => {
        const {editor, onChange} = setup([
            {type: AnnotationType.Rect, x: 0, y: 0, w: 1, h: 1},
        ]);
        expect(editor().annotations[0].id).toBe('k-0');
        act(() => editor().rename('k-0', 'Face'));
        expect(onChange).toHaveBeenCalledWith([
            expect.objectContaining({id: 'k-0', name: 'Face'}),
        ]);
    });

    it('applies the options to the selected shape and remembers them for its kind', () => {
        const {editor, onChange, sync} = setup([rect]);
        act(() => editor().select('r1'));
        expect(editor().options).toEqual({
            color: '#000000',
            size: 2,
            fillColor: undefined,
        });
        act(() =>
            editor().setOptions({
                color: '#ff0000',
                size: 10,
                fillColor: '#00ff00',
            })
        );
        expect(onChange).toHaveBeenLastCalledWith([
            expect.objectContaining({
                id: 'r1',
                c: '#ff0000',
                s: 0.01,
                f: '#00ff00',
            }),
        ]);
        sync();
        // Picking the tool again starts with the last options of the kind
        act(() => editor().setTool(AnnotationType.Rect));
        expect(editor().options).toEqual({
            color: '#ff0000',
            size: 10,
            fillColor: '#00ff00',
        });
        expect(editor().selectedId).toBeUndefined();
    });

    it('removes, duplicates and nudges the selection', () => {
        const {editor, onChange, sync} = setup([rect]);
        act(() => editor().select('r1'));
        act(() => editor().nudge(100, 50));
        expect(onChange).toHaveBeenLastCalledWith([
            expect.objectContaining({id: 'r1', x: 0.2, y: 0.2}),
        ]);
        sync();
        act(() => editor().duplicate('r1'));
        const copy = onChange.mock.lastCall![0][1];
        expect(copy.id).not.toBe('r1');
        expect(copy.name).toBe('Rectangle #2');
        sync();
        expect(editor().selectedId).toBe(copy.id);
        act(() => editor().remove(copy.id));
        expect(onChange).toHaveBeenLastCalledWith([
            expect.objectContaining({id: 'r1'}),
        ]);
        sync();
        expect(editor().selectedId).toBeUndefined();
    });

    it('creates a text once typed, updates an edited one and removes an emptied one', () => {
        const {editor, onChange, sync} = setup();
        const text = {
            type: AnnotationType.Text,
            x: 0.5,
            y: 0.5,
            text: '',
            s: 0.03,
        } as const;
        act(() => editor().editText({annotation: text}));
        act(() => editor().commitText('  '));
        expect(onChange).not.toHaveBeenCalled();
        act(() => editor().editText({annotation: text}));
        act(() => editor().commitText(' Hello '));
        const created = onChange.mock.lastCall![0][0];
        expect(created).toMatchObject({text: 'Hello'});
        expect(created.name).toBeUndefined();
        sync();
        act(() => editor().editText({id: created.id, annotation: created}));
        act(() => editor().commitText('Bye'));
        expect(onChange).toHaveBeenLastCalledWith([
            expect.objectContaining({id: created.id, text: 'Bye'}),
        ]);
        sync();
        act(() =>
            editor().editText({
                id: created.id,
                annotation: editor().annotations[0] as TextAnnotation,
            })
        );
        act(() => editor().commitText(''));
        expect(onChange).toHaveBeenLastCalledWith([]);
        expect(editor().textEdit).toBeUndefined();
    });

    it('follows the target: its highlighted shape, the tools reset', () => {
        const {hook, editor} = setup([rect]);
        act(() => editor().setTool(AnnotationType.Draw));
        hook.rerender({
            target: {
                key: 'other',
                annotations: [rect],
                selectedId: 'r1',
                editable: false,
            },
        });
        expect(editor().tool).toBeUndefined();
        expect(editor().selectedId).toBe('r1');
        expect(editor().active).toBe(false);
    });

    it('keeps the shapes of the other pages out of the layer', () => {
        const hook = renderHook(() =>
            useAnnotationEditor(
                {
                    key: 'doc',
                    annotations: [
                        {...rect, id: 'p1', page: 1},
                        {...rect, id: 'p2', page: 2},
                        {...rect, id: 'any'},
                    ],
                },
                2
            )
        );
        expect(hook.result.current.pageAnnotations.map(a => a.id)).toEqual([
            'p2',
            'any',
        ]);
    });
});
