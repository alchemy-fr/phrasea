# Annotations

Annotations are shapes drawn over an asset in the viewer: a target, a
rectangle, a circle, a line or an arrow, a free hand drawing or a text. They
are attached either to a **discussion message** or to a **value of an
attribute**.

## Format

A shape is a JSON object (see `AssetAnnotationsInterface` in the API and
`features/annotations/types.ts` in the client). Coordinates and sizes are
**relative to the rendered media (0..1)**, so the same shape fits every
rendition; `page` (1-based) targets a page of a document.

| `type`       | Fields                                  | Options                                   |
| ------------ | --------------------------------------- | ----------------------------------------- |
| `target`     | `x`, `y`                                | `c` (color), `s` (size)                   |
| `rect`       | `x`, `y`, `w`, `h`                      | `c` (border), `f` (fill), `s` (stroke)    |
| `circle`     | `x`, `y`, `r`                           | `c`, `f`, `s`                             |
| `line`       | `x1`, `y1`, `x2`, `y2`                  | `c`, `s`                                  |
| `arrow`      | same as `line`, the head on `x2`, `y2`  | `c`, `s`                                  |
| `draw`       | `paths` (list of lists of `{x, y}`)     | `c`, `s`                                  |
| `text`       | `x`, `y`, `text`                        | `c`, `s` (font size)                      |
| `cue`        | `t` (seconds)                           |                                           |
| `time_range` | `s`, `e` (seconds)                      |                                           |

Every shape also carries an `id` (unique within its list) and a `name` shown
by the viewer. The viewer only draws the shapes of the first group (time based
ones have no tool yet).

## Discussion messages

A message attachment of type `annotation` holds one shape:
`{"type": "annotation", "content": "<the shape as JSON>"}`. They are posted
with the message (`POST /messages`, `attachments`) and removed like any
attachment (`PATCH /messages/{id}`, `removeAttachments` with the shape ids).

In the client, the composer of the asset view offers to annotate the asset:
the shapes drawn are listed under the message and sent with it; the ones of a
posted message are shown on the asset when clicked.

## Attribute values

An attribute value may carry shapes in `assetAnnotations` (output) /
`annotations` (input of the batch update and of `POST /attributes`). The
`annotations` input is optional: **omitted (or null), the stored shapes are
kept; an empty list removes them**. The AWS Rekognition integration fills them
with the bounding boxes of the labels and texts it detects.

The attribute definition flag **`annotationsEnabled`** tells the GUI to offer
the drawing tools for the values of the attribute. A value that already has
shapes (from an integration) can be edited whatever the flag.

## Client

The viewer code lives in `databox/client/src/features/annotations/`:

- `AnnotationsProvider` shares the current *target* (the shapes shown, whether
  they are editable, and the callback receiving the edits) between the player
  and the side panel of an asset view; `useAnnotationTarget` lets a component
  (composer, attribute field, integration panel) own the target. While no
  component owns it, the player shows the *fallback* target: the shapes of
  every attribute value of the asset (`getAssetAnnotationsTarget`), read
  only, each named after its attribute. The toolbar hides them (remembered in
  the browser) and shows them again.
- `useAnnotationLayer` gives the player (`ZoomableImage`) the layer to render
  over the image, in a box following its zoom, pan and rotation, and the
  tools to put in its toolbar.
- `useAnnotationEditor` holds the state of the layer: the tool, the
  selection, the shape being dragged, the options of the toolbar; its actions
  (add, update, remove, duplicate, rename, nudge, text edit) send the list to
  the target after every edit.
- `AnnotationLayer` renders the shapes as SVG (React elements, so the
  browser does the hit testing) and handles the pointer: a tool draws a new
  shape, otherwise pressing a shape selects and moves it, pressing a handle
  resizes it. The pointer position is read through the SVG matrix
  (`getScreenCTM`), so the layer works whatever the transform of the image.
- `shapes.tsx` holds one model per shape type: bounding box, move, handles
  and resize, creation from a drag (a click gives a default size), options,
  and the SVG rendering. All geometry is computed in pixels of the rendered
  media; the shapes keep relative coordinates.
- `ShapeControl` is the floating panel of the selected shape (grip to drag
  it, name, edit text, duplicate, delete); `TextEditor` types the text of a
  shape in place.

Interactions: a click on a shape selects it and the same press drags it; a
click where shapes overlap cycles through them; Delete removes the selection,
the arrow keys move it (Shift: faster), Escape cancels the drag, leaves the
tool, then deselects; Space held lets the pointer pan the image. The grip of
the panel drags the shape too (handy for a thin or small shape). The text
tool opens the inline editor on click: Enter keeps the text, Shift+Enter
breaks the line (a text is drawn as one `tspan` per line); double-clicking a
text (or its pencil button) edits it again. The drawing tool stays active:
each stroke adds a path to the drawing started, until the tool is left.

The toolbar popovers (colors, size) are portaled out of the player; the
player ignores the pointer events bubbling from them (React bubbles through
portals), otherwise its pan would capture the pointer of their sliders.
