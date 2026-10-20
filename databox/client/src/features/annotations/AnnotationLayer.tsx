'use client';

import {
    useEffect,
    useRef,
    useState,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import {
    AnnotationType,
    type AssetAnnotation,
    type DrawAnnotation,
    type Point,
    type TextAnnotation,
} from './types';
import {distance, type MediaSize} from './geometry';
import {getShapeBox, getShapeModel, type HandleId} from './shapes';
import {draftId, type AnnotationEditor} from './useAnnotationEditor';
import {annotationZIndex, selectionColor} from './controls';
import {ShapeControl} from './ShapeControl';
import {TextEditor} from './TextEditor';

/** Pixels (on screen) the pointer may move during a click */
const dragThreshold = 3;

type Drag =
    | {
          kind: 'move';
          id: string;
          origin: AssetAnnotation;
          moved: boolean;
          /** Started from the grip of the panel, not from the shape */
          grip?: boolean;
      }
    | {kind: 'resize'; id: string; handle: HandleId; origin: AssetAnnotation}
    | {kind: 'create'; tool: AnnotationType}
    | {kind: 'draw'; points: Point[]};

type DragState = Drag & {start: Point; client: Point};

function isTyping(): boolean {
    const el = document.activeElement as HTMLElement | null;

    return (
        !!el &&
        (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) ||
            el.isContentEditable)
    );
}

/** The pointer position in pixels of the media, whatever the transforms */
function toLocal(
    svg: SVGSVGElement,
    e: {clientX: number; clientY: number}
): Point {
    const ctm = svg.getScreenCTM();
    if (!ctm) {
        const r = svg.getBoundingClientRect();

        return {x: e.clientX - r.left, y: e.clientY - r.top};
    }
    const p = new DOMPoint(e.clientX, e.clientY).matrixTransform(ctm.inverse());

    return {x: p.x, y: p.y};
}

function annotationIdOf(target: EventTarget | null): string | undefined {
    return (
        (target instanceof Element &&
            target
                .closest<SVGElement>('[data-annotation-id]')
                ?.getAttribute('data-annotation-id')) ||
        undefined
    );
}

function toRelativePath(points: Point[], media: MediaSize): Point[] {
    return points.map(p => ({x: p.x / media.width, y: p.y / media.height}));
}

/**
 * The shapes of the target as SVG over the image, with the pointer
 * handling: the tool draws a new shape, otherwise a press selects and drags
 * the shape (or one of its handles) under the pointer.
 */
export function AnnotationLayer({
    editor,
    zoom,
}: {
    editor: AnnotationEditor;
    /** Screen pixels per pixel of the media: controls keep their size */
    zoom: number;
}) {
    const {
        active,
        editable,
        media,
        tool,
        selectedId,
        draft,
        textEdit,
        setDraft,
    } = editor;
    const svgRef = useRef<SVGSVGElement>(null);
    const dragRef = useRef<DragState>(undefined);
    const spaceRef = useRef(false);
    const [spaceHeld, setSpaceHeld] = useState(false);
    // The drawing continued by the next strokes of the pen
    const drawingIdRef = useRef<string>(undefined);
    // The shape pressed last: the pointer capture retargets the clicks to
    // the svg, a double click would not know the shape otherwise
    const pressedIdRef = useRef<string>(undefined);
    const editorRef = useRef(editor);
    editorRef.current = editor;

    // The shapes follow the size of the image (window or panel resize)
    useEffect(() => {
        const svg = svgRef.current;
        if (!svg) {
            return;
        }
        const observer = new ResizeObserver(entries => {
            const {width, height} = entries[0].contentRect;
            if (width && height) {
                editorRef.current.setMedia({width, height});
            }
        });
        observer.observe(svg);

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (tool !== AnnotationType.Draw) {
            drawingIdRef.current = undefined;
        }
    }, [tool]);

    const cancelDrag = () => {
        const svg = svgRef.current;
        const drag = dragRef.current;
        dragRef.current = undefined;
        if (drag && svg) {
            editorRef.current.setDraft(undefined);
        }
    };

    // Space held: the pointer pans the image (the layer lets it through)
    useEffect(() => {
        if (!active) {
            spaceRef.current = false;
            setSpaceHeld(false);

            return;
        }
        const onKeyDown = (e: KeyboardEvent) => {
            if (e.key === ' ' && !isTyping()) {
                e.preventDefault();
                spaceRef.current = true;
                setSpaceHeld(true);
                cancelDrag();
            }
        };
        const onKeyUp = (e: KeyboardEvent) => {
            if (e.key === ' ') {
                spaceRef.current = false;
                setSpaceHeld(false);
            }
        };
        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('keyup', onKeyUp);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('keyup', onKeyUp);
        };
    }, [active]);

    // Delete removes the selected shape, the arrows move it, Escape cancels
    // the drag, leaves the tool, then deselects; before the viewer gets the
    // key. A read only layer only deselects.
    useEffect(() => {
        if (editable && !active) {
            return;
        }
        const onKeyDown = (e: KeyboardEvent) => {
            if (isTyping()) {
                return;
            }
            const ed = editorRef.current;
            if (e.key === 'Escape') {
                if (dragRef.current) {
                    cancelDrag();
                } else if (ed.tool) {
                    ed.setTool(undefined);
                } else if (ed.selectedId) {
                    ed.select(undefined);
                } else {
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();

                return;
            }
            if (!ed.selected || !active) {
                return;
            }
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                ed.remove(ed.selected.id!);
            } else if (e.key.startsWith('Arrow')) {
                const step = (e.shiftKey ? 10 : 1) / zoom;
                const dx =
                    e.key === 'ArrowLeft'
                        ? -step
                        : e.key === 'ArrowRight'
                          ? step
                          : 0;
                const dy =
                    e.key === 'ArrowUp'
                        ? -step
                        : e.key === 'ArrowDown'
                          ? step
                          : 0;
                e.preventDefault();
                e.stopImmediatePropagation();
                ed.nudge(dx, dy);
            }
        };
        window.addEventListener('keydown', onKeyDown, true);

        return () => window.removeEventListener('keydown', onKeyDown, true);
    }, [editable, active, zoom]);

    const onPointerDown = (e: ReactPointerEvent<SVGSVGElement>) => {
        const svg = e.currentTarget;
        if (spaceRef.current || e.button !== 0 || !media.width) {
            return;
        }
        if (!editable) {
            // Read only: a shape is selected to show its name, the rest of
            // the image stays the player's
            const id = annotationIdOf(e.target);
            pressedIdRef.current = id;
            if (!id) {
                return;
            }
            e.stopPropagation();
            if (id !== selectedId) {
                editor.select(id);
            } else if (!cycleSelection(e.clientX, e.clientY, id)) {
                editor.select(undefined);
            }

            return;
        }
        if (!active) {
            return;
        }
        // The image is not panned, the shape is not zoomed on
        e.stopPropagation();
        if (isTyping()) {
            (document.activeElement as HTMLElement).blur();
        }
        const p = toLocal(svg, e);
        const client = {x: e.clientX, y: e.clientY};
        svg.setPointerCapture(e.pointerId);

        if (tool) {
            if (tool === AnnotationType.Draw) {
                dragRef.current = {
                    kind: 'draw',
                    points: [p],
                    start: p,
                    client,
                };
            } else {
                dragRef.current = {kind: 'create', tool, start: p, client};
            }

            return;
        }

        const handle = (e.target as Element).closest<SVGElement>(
            '[data-handle]'
        );
        const selected = editor.selected;
        if (handle && selected && editable) {
            dragRef.current = {
                kind: 'resize',
                id: selected.id!,
                handle: handle.getAttribute('data-handle') as HandleId,
                origin: selected,
                start: p,
                client,
            };

            return;
        }

        const id = annotationIdOf(e.target);
        pressedIdRef.current = id;
        if (!id) {
            editor.select(undefined);

            return;
        }
        const annotation = editor.pageAnnotations.find(a => a.id === id);
        if (!annotation) {
            return;
        }
        if (id !== selectedId) {
            editor.select(id);
        }
        if (editable) {
            dragRef.current = {
                kind: 'move',
                id,
                origin: annotation,
                moved: false,
                start: p,
                client,
            };
        }
    };

    /** The grip of the panel pressed: the svg captures the pointer and
     * moves the shape as if it had been pressed itself */
    const onGripDown = (e: ReactPointerEvent, annotation: AssetAnnotation) => {
        const svg = svgRef.current;
        if (!svg || e.button !== 0 || !media.width || !active) {
            return;
        }
        svg.setPointerCapture(e.pointerId);
        dragRef.current = {
            kind: 'move',
            id: annotation.id!,
            origin: annotation,
            moved: false,
            grip: true,
            start: toLocal(svg, e),
            client: {x: e.clientX, y: e.clientY},
        };
    };

    /** The drawing in progress: the stroke added to the one continued */
    const drawingDraft = (points: Point[]): DrawAnnotation => {
        const current = editor.annotations.find(
            a => a.id === drawingIdRef.current
        ) as DrawAnnotation | undefined;
        const path = toRelativePath(points, media);
        if (current) {
            const ox = current.x ?? 0;
            const oy = current.y ?? 0;

            return {
                ...current,
                paths: [
                    ...current.paths,
                    path.map(q => ({x: q.x - ox, y: q.y - oy})),
                ],
            };
        }
        const model = getShapeModel(AnnotationType.Draw)!;

        return {
            ...(model.create(
                points[0],
                points[0],
                editor.options,
                media
            ) as DrawAnnotation),
            paths: [path],
            id: draftId,
        };
    };

    const onPointerMove = (e: ReactPointerEvent<SVGSVGElement>) => {
        const drag = dragRef.current;
        if (!drag || !media.width) {
            return;
        }
        const p = toLocal(e.currentTarget, e);
        const movedOnScreen =
            distance(drag.client, {x: e.clientX, y: e.clientY}) > dragThreshold;

        switch (drag.kind) {
            case 'move': {
                if (!drag.moved && !movedOnScreen) {
                    return;
                }
                drag.moved = true;
                const model = getShapeModel(drag.origin.type);
                if (model) {
                    setDraft(
                        model.move(
                            drag.origin,
                            p.x - drag.start.x,
                            p.y - drag.start.y,
                            media
                        )
                    );
                }
                break;
            }
            case 'resize': {
                const model = getShapeModel(drag.origin.type);
                if (model) {
                    setDraft(model.resize(drag.origin, drag.handle, p, media));
                }
                break;
            }
            case 'create': {
                if (!movedOnScreen || drag.tool === AnnotationType.Text) {
                    return;
                }
                const model = getShapeModel(drag.tool);
                if (model) {
                    setDraft({
                        ...model.create(drag.start, p, editor.options, media),
                        id: draftId,
                    });
                }
                break;
            }
            case 'draw': {
                drag.points.push(p);
                setDraft(drawingDraft(drag.points));
                break;
            }
        }
    };

    const onPointerUp = (e: ReactPointerEvent<SVGSVGElement>) => {
        const drag = dragRef.current;
        dragRef.current = undefined;
        if (!drag || !media.width) {
            return;
        }
        const p = toLocal(e.currentTarget, e);
        setDraft(undefined);

        switch (drag.kind) {
            case 'move': {
                const model = getShapeModel(drag.origin.type);
                if (drag.moved && model) {
                    editor.update(
                        drag.id,
                        model.move(
                            drag.origin,
                            p.x - drag.start.x,
                            p.y - drag.start.y,
                            media
                        )
                    );
                } else if (
                    !drag.moved &&
                    !drag.grip &&
                    drag.id === selectedId
                ) {
                    cycleSelection(e.clientX, e.clientY, drag.id);
                }
                break;
            }
            case 'resize': {
                const model = getShapeModel(drag.origin.type);
                if (model) {
                    editor.update(
                        drag.id,
                        model.resize(drag.origin, drag.handle, p, media)
                    );
                }
                break;
            }
            case 'create': {
                const model = getShapeModel(drag.tool);
                if (!model) {
                    break;
                }
                editor.setTool(undefined);
                if (drag.tool === AnnotationType.Text) {
                    editor.editText({
                        annotation: model.create(
                            drag.start,
                            drag.start,
                            editor.options,
                            media
                        ) as TextAnnotation,
                    });
                } else {
                    editor.add(
                        model.create(drag.start, p, editor.options, media)
                    );
                }
                break;
            }
            case 'draw': {
                const drawing = drawingDraft(drag.points);
                if (drawing.id === draftId) {
                    const {id: _id, ...created} = drawing;
                    drawingIdRef.current = editor.add(created).id;
                } else {
                    editor.update(drawing.id!, drawing);
                }
                break;
            }
        }
    };

    /** Clicking again where shapes overlap selects the next one below */
    const cycleSelection = (
        clientX: number,
        clientY: number,
        id: string
    ): boolean => {
        const svg = svgRef.current;
        if (!svg) {
            return false;
        }
        const ids: string[] = [];
        for (const el of document.elementsFromPoint(clientX, clientY)) {
            if (!svg.contains(el)) {
                continue;
            }
            const other = annotationIdOf(el);
            if (other && !ids.includes(other)) {
                ids.push(other);
            }
        }
        if (ids.length > 1) {
            editor.select(ids[(ids.indexOf(id) + 1) % ids.length]);

            return true;
        }

        return false;
    };

    const onDoubleClick = (e: React.MouseEvent<SVGSVGElement>) => {
        if (!active) {
            if (pressedIdRef.current) {
                e.stopPropagation();
            }

            return;
        }
        e.stopPropagation();
        const id = pressedIdRef.current;
        const annotation = editor.pageAnnotations.find(a => a.id === id);
        if (editable && annotation?.type === AnnotationType.Text) {
            editor.editText({id, annotation: annotation as TextAnnotation});
        }
    };

    // The shape being dragged replaces the stored one; a new one is appended
    const shown = editor.pageAnnotations.map(a =>
        draft && draft.id === a.id ? draft : a
    );
    if (draft?.id === draftId) {
        shown.push(draft);
    }
    const selected = shown.find(a => a.id === selectedId);
    const selectedBox =
        selected && media.width ? getShapeBox(selected, media) : undefined;
    const dragging = !!draft;
    const unscale = 1 / zoom;
    const hitWidth = 14 * unscale;
    const handleRadius = 5 * unscale;
    const shapeCursor = tool ? 'crosshair' : editable ? 'move' : 'pointer';

    return (
        <>
            <svg
                ref={svgRef}
                data-testid="annotation-layer"
                data-active={active ? 'true' : undefined}
                width="100%"
                height="100%"
                style={{
                    position: 'absolute',
                    inset: 0,
                    zIndex: annotationZIndex + 1,
                    overflow: 'visible',
                    cursor: tool ? 'crosshair' : 'default',
                    pointerEvents: active && !spaceHeld ? 'auto' : 'none',
                    touchAction: 'none',
                    userSelect: 'none',
                }}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onPointerCancel={cancelDrag}
                onClick={e =>
                    (active || annotationIdOf(e.target)) && e.stopPropagation()
                }
                onDoubleClick={onDoubleClick}
            >
                {media.width
                    ? shown.map(annotation => {
                          const model = getShapeModel(annotation.type);
                          if (
                              !model ||
                              (textEdit?.id && textEdit.id === annotation.id)
                          ) {
                              return null;
                          }

                          return (
                              <g
                                  key={annotation.id}
                                  data-annotation-id={annotation.id}
                                  data-annotation-type={annotation.type}
                                  style={{
                                      cursor: shapeCursor,
                                      // Read only: the shapes answer the
                                      // pointer, not the layer around them
                                      pointerEvents:
                                          annotation.id === draftId
                                              ? 'none'
                                              : editable
                                                ? undefined
                                                : 'visiblePainted',
                                  }}
                              >
                                  {model.render({annotation, media, hitWidth})}
                              </g>
                          );
                      })
                    : null}
                {selected && selectedBox && !tool ? (
                    <g style={{pointerEvents: dragging ? 'none' : undefined}}>
                        <rect
                            x={selectedBox.x - 4 * unscale}
                            y={selectedBox.y - 4 * unscale}
                            width={selectedBox.w + 8 * unscale}
                            height={selectedBox.h + 8 * unscale}
                            fill="none"
                            stroke={selectionColor}
                            strokeWidth={unscale}
                            strokeDasharray={`${4 * unscale} ${3 * unscale}`}
                            style={{pointerEvents: 'none'}}
                        />
                        {editable
                            ? getShapeModel(selected.type)
                                  ?.getHandles(selected, media)
                                  .map(h => (
                                      <circle
                                          key={h.id}
                                          data-handle={h.id}
                                          cx={h.x}
                                          cy={h.y}
                                          r={handleRadius}
                                          fill="#fff"
                                          stroke={selectionColor}
                                          strokeWidth={1.5 * unscale}
                                          style={{cursor: h.cursor}}
                                      />
                                  ))
                            : null}
                    </g>
                ) : null}
            </svg>
            {selected && selectedBox && !dragging && !tool && !textEdit ? (
                <ShapeControl
                    annotation={selected}
                    box={selectedBox}
                    media={media}
                    zoom={zoom}
                    editable={editable}
                    onDragStart={e => onGripDown(e, selected)}
                    onDelete={() => editor.remove(selected.id!)}
                    onDuplicate={() => editor.duplicate(selected.id!)}
                    onRename={name => editor.rename(selected.id!, name)}
                    onEditText={
                        selected.type === AnnotationType.Text
                            ? () =>
                                  editor.editText({
                                      id: selected.id,
                                      annotation: selected as TextAnnotation,
                                  })
                            : undefined
                    }
                />
            ) : null}
            {textEdit && media.width ? (
                <TextEditor
                    key={textEdit.id ?? draftId}
                    edit={textEdit}
                    media={media}
                    zoom={zoom}
                    onCommit={editor.commitText}
                    onCancel={() => editor.editText(undefined)}
                />
            ) : null}
        </>
    );
}
