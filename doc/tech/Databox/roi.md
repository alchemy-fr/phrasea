# Regions of interest

An asset can have a **region of interest** (ROI): time ranges of its video or audio
source, and/or a crop of its image or video source. A basket item can have its own
**extract**, with the same format. The source file is never modified: the clip is
applied when the media is shown, and on a temporary copy when it is exported or
published.

## Model

The ROI is stored in `asset.roi`, the extract of a basket item in
`basket_asset.context.clip`, both as (`App\Model\MediaClip`):

```json
{
    "timeRanges": [
        {"s": 12.0, "e": 30.5},
        {"s": 50, "e": 61.2}
    ],
    "crop": {"x": 0.1, "y": 0.2, "w": 0.5, "h": 0.5}
}
```

- Times are in seconds. The crop is relative (0..1) to the displayed media, rotation
  applied.
- The ranges are normalized: sorted by start, overlapping or contiguous ones merged,
  empty ones dropped. At most 50 ranges (`MediaClip::MAX_TIME_RANGES`).
- Time ranges only apply to videos and audios, a crop to images (not SVG) and videos:
  the API answers 400 otherwise, or when the asset has no source file.
- The former single range format of the extracts (`{"timeRange": {"s": …, "e": …}}`)
  is still read, and served in the current format.

The ROI is set with `PATCH /assets/{id}` (`{"roi": …}`, `null` or `{}` removes it), so
only a user who can edit the asset can change it. The extract of a basket item is set
with `PATCH /basket-assets/{id}` (`{"clip": …}`).

**Replacing the source file removes the ROI**: the new file may differ in type,
duration or framing. A ROI given in the same request applies to the new file.

## Priority

The extract of a basket item **replaces** the ROI of its asset as a whole (they are
not intersected). A basket item without an extract inherits the ROI of its asset
(`ClipResolver`).

## Where it applies

- The asset viewer, the grid thumbnails and the hover preview show the ROI (the
  extract, in a basket). A button of the viewer shows the whole asset, the ROI over it.
- Exports (`POST /asset-exports`) and Expose publications cut and crop the files.
- It does not apply to shares, to the remote export automation (`RemoteExportAction`),
  nor to the Renditions and Versions tabs: they deliver the whole files. No rendition
  is generated for the ROI.

## Several time ranges

The exports and the publications cut each range, with the same settings, then
concatenate them in their order: an asset stays a single file. The ranges starting
after the end of the media are ignored; when none is left, the file is not exported
(nor published) rather than delivered whole. The players play the ranges one after
the other.

## Whole files

- Export: `ignoreRoi: true` exports the whole files, ignoring the ROIs and the extracts.
- Expose: the "publish the whole files" option of a new publication does the same. It
  is stored with the synchronization (`IntegrationData`, `{"id": …, "ignoreRoi": …}`)
  and fixed for its life: to change it, stop the publication and create a new one.

A change of the ROI of an asset synchronizes the baskets holding it again
(`AssetRoiUpdate`): their publications are updated as the clip hashes change.
