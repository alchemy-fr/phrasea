import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {shortId} from '@/lib/utils/misc';
import type {AnnotationTarget} from './AnnotationsProvider';
import {
    AnnotationType,
    type AnnotationOptions,
    type AssetAnnotation,
    type TextAnnotation,
} from './types';
import {emptyMedia, type MediaSize} from './geometry';
import {getShapeModel} from './shapes';
import {getDefaultOptions, updateLastOptions} from './defaultOptions';
import {ensureAnnotationIds, toStoredAnnotation} from './common';
import {getDefaultAnnotationName} from './annotationNames';

/** A text being typed: an existing shape, or a new one when `id` is unset */
export type TextEdit = {
    id?: string;
    annotation: TextAnnotation;
};

export type AnnotationEditor = {
    /** Every shape of the target, with an id */
    annotations: AssetAnnotation[];
    /** The shapes of the displayed page */
    pageAnnotations: AssetAnnotation[];
    page: number | undefined;
    editable: boolean;
    /** The pointer draws and selects shapes (editable and not paused) */
    active: boolean;
    /** Size of the rendered media, reported by the layer */
    media: MediaSize;
    setMedia: (media: MediaSize) => void;
    /** The drawing tool, none in selection mode */
    tool: AnnotationType | undefined;
    setTool: (tool: AnnotationType | undefined) => void;
    /** False pauses the tools (the pointer pans the image) */
    annotate: boolean;
    setAnnotate: (annotate: boolean) => void;
    /** Options of the tool, or of the selected shape */
    options: AnnotationOptions;
    /** Applies to the selected shape and is remembered for its kind */
    setOptions: (options: AnnotationOptions) => void;
    selectedId: string | undefined;
    selected: AssetAnnotation | undefined;
    select: (id: string | undefined) => void;
    /** The shape being dragged or drawn, shown instead of the stored one */
    draft: AssetAnnotation | undefined;
    setDraft: (draft: AssetAnnotation | undefined) => void;
    textEdit: TextEdit | undefined;
    editText: (edit: TextEdit | undefined) => void;
    commitText: (text: string) => void;
    /** Adds a shape (named, on the page) and selects it */
    add: (annotation: AssetAnnotation) => AssetAnnotation;
    update: (id: string, annotation: AssetAnnotation) => void;
    remove: (id: string) => void;
    duplicate: (id: string) => void;
    rename: (id: string, name: string) => void;
    /** Moves the selected shape by a delta in pixels of the media */
    nudge: (dx: number, dy: number) => void;
};

const noAnnotations: AssetAnnotation[] = [];

/** Id of a shape being created, not stored yet */
export const draftId = '__draft__';

/**
 * State of the annotation layer of a target: the tool, the selection, the
 * shape being dragged, the options; and the edits, sent to the target.
 */
export function useAnnotationEditor(
    target: AnnotationTarget | undefined,
    page: number | undefined
): AnnotationEditor {
    const {t} = useTranslation();
    const editable = !!target?.editable;
    const targetKey = target?.key;
    const [media, setMedia] = useState<MediaSize>(emptyMedia);
    const [tool, setToolState] = useState<AnnotationType>();
    const [annotate, setAnnotateState] = useState(true);
    const [options, setOptionsState] = useState<AnnotationOptions>(() =>
        getDefaultOptions(AnnotationType.Rect)
    );
    const [selectedId, setSelectedId] = useState<string>();
    const [draft, setDraft] = useState<AssetAnnotation>();
    const [textEdit, setTextEdit] = useState<TextEdit>();

    const annotations = useMemo(
        () =>
            ensureAnnotationIds(
                target?.annotations ?? noAnnotations,
                targetKey ?? 'annotation'
            ),
        [target?.annotations, targetKey]
    );
    const pageAnnotations = useMemo(
        () => annotations.filter(a => !page || !a.page || a.page === page),
        [annotations, page]
    );

    // The actions read the latest values without being recreated
    const latest = useRef({annotations, media, selectedId, tool, options});
    latest.current = {annotations, media, selectedId, tool, options};
    const onChangeRef = useRef(target?.onChange);
    onChangeRef.current = target?.onChange;

    // Another target: the tools start afresh, its shape to highlight is
    // selected
    const targetSelectedId = target?.selectedId;
    useEffect(() => {
        setToolState(undefined);
        setAnnotateState(true);
        setDraft(undefined);
        setTextEdit(undefined);
    }, [targetKey]);
    useEffect(() => {
        setSelectedId(targetSelectedId);
    }, [targetKey, targetSelectedId]);

    const emit = useCallback((next: AssetAnnotation[]) => {
        onChangeRef.current?.(next.map(toStoredAnnotation));
    }, []);

    const update = useCallback(
        (id: string, annotation: AssetAnnotation) => {
            emit(
                latest.current.annotations.map(a =>
                    a.id === id ? {...annotation, id} : a
                )
            );
        },
        [emit]
    );

    const add = useCallback(
        (annotation: AssetAnnotation): AssetAnnotation => {
            const {annotations} = latest.current;
            const created: AssetAnnotation = {
                ...annotation,
                id: `annotation-${shortId()}`,
                page,
            };
            if (created.page === undefined) {
                delete created.page;
            }
            if (!created.name && created.type !== AnnotationType.Text) {
                created.name = getDefaultAnnotationName(
                    t,
                    created,
                    annotations
                );
            }
            emit([...annotations, created]);
            setSelectedId(created.id);

            return created;
        },
        [emit, page, t]
    );

    const remove = useCallback(
        (id: string) => {
            emit(latest.current.annotations.filter(a => a.id !== id));
            setSelectedId(s => (s === id ? undefined : s));
        },
        [emit]
    );

    const duplicate = useCallback(
        (id: string) => {
            const {annotations, media} = latest.current;
            const annotation = annotations.find(a => a.id === id);
            const model = getShapeModel(annotation?.type);
            if (!annotation || !model) {
                return;
            }
            const offset = Math.max(10, media.width * 0.02);
            const {id: _id, name: _name, ...copy} = annotation;
            add(model.move(copy, offset, offset, media));
        },
        [add]
    );

    const rename = useCallback(
        (id: string, name: string) => {
            const annotation = latest.current.annotations.find(
                a => a.id === id
            );
            if (annotation) {
                update(id, {...annotation, name});
            }
        },
        [update]
    );

    const select = useCallback((id: string | undefined) => {
        const {annotations, media} = latest.current;
        setSelectedId(id);
        const annotation = id ? annotations.find(a => a.id === id) : undefined;
        const model = getShapeModel(annotation?.type);
        if (annotation && model && media.width) {
            setOptionsState(model.toOptions(annotation, media));
        }
    }, []);

    const setTool = useCallback((next: AnnotationType | undefined) => {
        setToolState(next);
        setDraft(undefined);
        if (next) {
            setTextEdit(undefined);
            setSelectedId(undefined);
            setOptionsState(getDefaultOptions(next));
        }
    }, []);

    const setAnnotate = useCallback(
        (next: boolean) => {
            setAnnotateState(next);
            if (!next) {
                setTool(undefined);
            }
        },
        [setTool]
    );

    const setOptions = useCallback(
        (next: AnnotationOptions) => {
            const {annotations, media, selectedId, tool} = latest.current;
            setOptionsState(next);
            const selected = selectedId
                ? annotations.find(a => a.id === selectedId)
                : undefined;
            const type = (selected?.type ?? tool) as AnnotationType | undefined;
            if (type) {
                updateLastOptions(type, next);
            }
            const model = getShapeModel(selected?.type);
            if (selected && model && media.width) {
                update(selected.id!, model.applyOptions(selected, next, media));
            }
        },
        [update]
    );

    const nudge = useCallback(
        (dx: number, dy: number) => {
            const {annotations, media, selectedId} = latest.current;
            const selected = annotations.find(a => a.id === selectedId);
            const model = getShapeModel(selected?.type);
            if (selected && model && media.width) {
                update(selected.id!, model.move(selected, dx, dy, media));
            }
        },
        [update]
    );

    const commitText = useCallback(
        (text: string) => {
            setTextEdit(edit => {
                if (!edit) {
                    return undefined;
                }
                const trimmed = text.trim();
                if (edit.id) {
                    if (trimmed) {
                        update(edit.id, {...edit.annotation, text: trimmed});
                    } else {
                        remove(edit.id);
                    }
                } else if (trimmed) {
                    add({...edit.annotation, text: trimmed});
                }

                return undefined;
            });
        },
        [add, update, remove]
    );

    const selected = useMemo(
        () =>
            selectedId ? annotations.find(a => a.id === selectedId) : undefined,
        [annotations, selectedId]
    );

    return {
        annotations,
        pageAnnotations,
        page,
        editable,
        active: editable && annotate,
        media,
        setMedia,
        tool,
        setTool,
        annotate,
        setAnnotate,
        options,
        setOptions,
        selectedId,
        selected,
        select,
        draft,
        setDraft,
        textEdit,
        editText: setTextEdit,
        commitText,
        add,
        update,
        remove,
        duplicate,
        rename,
        nudge,
    };
}
