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
  (composer, attribute field, integration panel) own the target.
- `useAnnotationLayer` renders the canvas over the image (`ZoomableImage`),
  following its zoom, pan and rotation, with the toolbar of tools.
- `shapes/` holds one handler per shape type: how it is drawn with the mouse,
  rendered, selected, moved and resized.
