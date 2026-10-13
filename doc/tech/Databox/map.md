# Map of the results

The databox client can lay out the search results on a Mapbox map ("Map" in the
display options of the results), by one of their **geo point** attributes.

## Configuration

- `MAPBOX_TOKEN`: a Mapbox public access token (`pk.…`), passed to the `databox-client`
  service. Without it, the map layout is not offered, and a map preference falls back
  to the grid.
- At least one searchable attribute of type `geo_point` (`Location`…). It can be
  filled from the EXIF GPS tags with an initial value reading `Composite:GPSPosition`
  (see [metadata](./metadata.md)). When several geo point attributes exist, the map
  offers a selector.

In dev, the fixtures (`bin/dev/reset-db-fixtures.sh`) place the images and videos of the
**Newspaper** workspace around real cities (`App\Fixture\Faker\GeoPlaceFaker`), with
`City` and `Country` matching their `Location`; audio and PDF assets have no location.

## Clusters

The map does not place the loaded pages of results: it asks the API to cluster the
positions of **all** the assets matching the search.

`GET /assets/geo-clusters` takes the search parameters of `GET /assets` (`query`,
`conditions[]`, `workspaces[]`, `parents[]`, `collection`, `story`, `savedSearch`)
and:

| Parameter   | Description                                                                  |
| ----------- | ---------------------------------------------------------------------------- |
| `field`     | the attribute key of the geo point attribute (`location_geo-point_s`)        |
| `precision` | tile zoom level of the clusters, 0 to 29 (the client sends the map zoom + 2) |
| `bounds`    | `top,left,bottom,right`: the area to cluster (the whole world when omitted)  |

It runs a `geotile_grid` aggregation (with a `geo_centroid`, a `geo_bounds` and, for
single assets, the asset ID) restricted to the workspaces where the user can read the
attribute, and returns:

```json
{
  "total": 1250,
  "geolocated": 830,
  "bounds": {"top": 51.1, "left": -4.8, "bottom": 42.3, "right": 8.2},
  "clusters": [
    {"key": "7/64/44", "count": 112, "lat": 48.85, "lng": 2.34, "bounds": {…}, "assetId": null},
    {"key": "7/66/45", "count": 1, "lat": 45.76, "lng": 4.83, "bounds": {…}, "assetId": "…"}
  ]
}
```

At most 2,000 clusters are returned. The client draws single assets as thumbnail
markers (up to 300), the others as bubbles.

## Searching from the map

The map writes regular AQL conditions, so they show in the conditions bar, can be
disabled or edited, and are kept in the URL and saved searches:

- **Zones** (condition `map-zones`): circles drawn on the map, combined with `OR`:
  `location WITHIN CIRCLE (48.85, 2.35, "1500m") OR location WITHIN CIRCLE (…)`.
- **Area of a cluster** (condition `map-area`): "Search this area" in the bubble of a
  cluster, `location WITHIN RECTANGLE (…)` around its positions.
