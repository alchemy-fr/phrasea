'use client';

import 'mapbox-gl/dist/mapbox-gl.css';
import './map.css';
import {
    useCallback,
    useEffect,
    useEffectEvent,
    useMemo,
    useRef,
    useState,
} from 'react';
import {createPortal} from 'react-dom';
import {
    FullscreenControl,
    GeolocateControl,
    type GeoJSONSource,
    Map as MapboxMap,
    type MapMouseEvent,
    type MapTouchEvent,
    Marker,
    NavigationControl,
    type Point,
    Popup,
    ScaleControl,
} from 'mapbox-gl';
import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {useShallow} from 'zustand/react/shallow';
import {useTranslation} from 'react-i18next';
import {useTheme} from 'next-themes';
import {
    CircleDashedIcon,
    LogInIcon,
    MapPinOffIcon,
    SatelliteIcon,
    ScanSearchIcon,
    XIcon,
} from 'lucide-react';
import type {Asset} from '@/types/api';
import {
    GeoBounds,
    GeoCluster,
    geoClusterSearch,
    getAsset,
    getGeoClusters,
    searchAssets,
} from '@/lib/api/assets';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useResults} from '@/features/search/ResultProvider';
import {useSearch} from '@/features/search/SearchProvider';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {useAssetStore} from '@/features/assets/assetStore';
import {Button} from '@/components/ui/button';
import {Toggle} from '@/components/ui/controls';
import {Spinner} from '@/components/ui/loader';
import {EmptyState, Separator} from '@/components/ui/misc';
import {Tooltip} from '@/components/ui/overlays';
import {SimpleSelect} from '@/components/ui/select';
import {formatNumber} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';
import {
    circleRing,
    circlesToAQL,
    clusterBounds,
    compactCount,
    coversArea,
    distance,
    formatDistance,
    formatLatLng,
    GeoCircle,
    hitZone,
    LngLat,
    MAP_AREA_CONDITION_ID,
    mapZonesConditionId,
    moveCircle,
    padBounds,
    parseCircles,
    round,
    tilePrecision,
} from './geo';
import {useMapStore} from './mapStore';
import {GeoField, useGeoFields} from './useGeoFields';
import {resolveMapColors, type MapColors} from './mapColors';
import {AssetPin} from './AssetPin';
import {ClusterPopup, clusterCondition, ZonePopup} from './MapPopups';
import {PreviewProvider} from '@/features/assets/list/preview/PreviewProvider';

const styleUrls = {
    light: 'mapbox://styles/mapbox/streets-v12',
    dark: 'mapbox://styles/mapbox/dark-v11',
    satellite: 'mapbox://styles/mapbox/satellite-streets-v12',
};

/**
 * Beyond this number of single assets in the clustered area, they are drawn
 * as bubbles too: a thumbnail marker is a DOM element.
 */
const MAX_PINS = 300;
/** Below this drag distance, drawing a zone is taken for a stray click */
const MIN_ZONE_PIXELS = 8;
/** Distance to the outline of a zone within which a press resizes it */
const EDGE_PIXELS = {mouse: 8, touch: 16};
/** Below this drag distance, a press on a zone is a click (its popup) */
const CLICK_PIXELS = 3;

type Viewport = {
    precision: number;
    bounds: GeoBounds | undefined;
};

type PopupTarget =
    | {kind: 'cluster'; cluster: GeoCluster}
    | {kind: 'zone'; index: number; at: LngLat};

/**
 * A zone being moved or resized: shown instead of the condition's one until
 * the condition, written at the release, gives new circles.
 */
type ZoneEdit = {
    base: GeoCircle[];
    index: number;
    circle: GeoCircle;
    /** Still held */
    active: boolean;
};

type FeatureCollection = Extract<
    Parameters<GeoJSONSource['setData']>[0],
    {type: 'FeatureCollection'}
>;
type Feature = FeatureCollection['features'][number];

/**
 * The results of the search placed on a map by one of their geo point
 * attributes. Positions are clustered by the API, so the map shows all the
 * results, not only the loaded pages.
 */
export default function AssetMap() {
    const {t} = useTranslation();
    const token = useConfig().mapbox?.token;
    const {fields, loaded} = useGeoFields();
    const {status, login, redirecting} = useAuth();
    const fieldKey = useMapStore(s => s.field);
    const field = fields.find(f => f.searchSlug === fieldKey) ?? fields[0];

    // The layout is only offered with a token
    if (!token) {
        return null;
    }
    if (!loaded) {
        return (
            <div className="flex h-full items-center justify-center">
                <Spinner />
            </div>
        );
    }
    if (!field) {
        // Signed out, only the attributes of the public workspaces are listed
        const anonymous = status === 'anonymous';

        return (
            <EmptyState
                testId="map-no-field"
                className="h-full"
                icon={<MapPinOffIcon />}
                title={t('map.no_field', 'No geolocation attribute')}
                description={
                    anonymous
                        ? t(
                              'map.no_field_anonymous',
                              'You are not signed in: the geolocation attributes of the workspaces may only be available to signed-in users. Sign in to place the assets on the map.'
                          )
                        : t(
                              'map.no_field_description',
                              'Add a "geo point" attribute to your workspaces to place the assets on the map.'
                          )
                }
                action={
                    anonymous ? (
                        <Button
                            data-testid="map-no-field-sign-in"
                            loading={redirecting}
                            onClick={() => login()}
                        >
                            <LogInIcon /> {t('user.login', 'Sign in')}
                        </Button>
                    ) : undefined
                }
            />
        );
    }

    return <MapView token={token} field={field} fields={fields} />;
}

function MapView({
    token,
    field,
    fields,
}: {
    token: string;
    field: GeoField;
    fields: GeoField[];
}) {
    const {t, i18n} = useTranslation();
    const results = useResults();
    const search = useSearch();
    const openAsset = useAssetOpener();
    const {resolvedTheme} = useTheme();
    const mapStyle = useMapStore(s => s.style);
    const setMapPreferences = useMapStore(s => s.set);
    const storeAssets = useAssetStore(s => s.setAssets);

    const containerRef = useRef<HTMLDivElement>(null);
    const [map, setMap] = useState<MapboxMap | null>(null);
    /** Increments every time the style (re)loads: its sources must be filled again */
    const [styleGeneration, setStyleGeneration] = useState(0);
    const [viewport, setViewport] = useState<Viewport>();
    const [drawing, setDrawing] = useState(false);
    const [draft, setDraft] = useState<GeoCircle | null>(null);
    const [zoneEdit, setZoneEdit] = useState<ZoneEdit | null>(null);
    const [popup, setPopup] = useState<PopupTarget | null>(null);
    const [popupNode] = useState(() => document.createElement('div'));
    /** A restored view is kept; otherwise the first results are framed */
    const framed = useRef(!!useMapStore.getState().view);

    const styleUrl =
        mapStyle === 'satellite'
            ? styleUrls.satellite
            : resolvedTheme === 'dark'
              ? styleUrls.dark
              : styleUrls.light;

    // Map ----------------------------------------------------------------------

    useEffect(() => {
        const container = containerRef.current!;
        const view = useMapStore.getState().view;
        const m = new MapboxMap({
            container,
            accessToken: token,
            style: styleUrl,
            center: view?.center ?? [10, 30],
            zoom: view?.zoom ?? 1.5,
            language: i18n.language,
        });
        m.addControl(
            new NavigationControl({visualizePitch: false}),
            'top-right'
        );
        m.addControl(
            new GeolocateControl({fitBoundsOptions: {maxZoom: 13}}),
            'top-right'
        );
        m.addControl(
            new FullscreenControl({
                container: container.parentElement ?? undefined,
            }),
            'top-right'
        );
        m.addControl(new ScaleControl(), 'bottom-left');

        m.on('style.load', () => {
            addLayers(m, resolveMapColors());
            setStyleGeneration(g => g + 1);
        });

        const onMoveEnd = () => {
            const b = m.getBounds();
            if (!b) {
                return;
            }
            const zoom = m.getZoom();
            const precision = tilePrecision(zoom);
            const visible = {
                west: b.getWest(),
                south: b.getSouth(),
                east: b.getEast(),
                north: b.getNorth(),
            };
            setViewport(prev =>
                prev &&
                prev.precision === precision &&
                coversArea(prev.bounds, visible)
                    ? prev
                    : {precision, bounds: clusterBounds(visible)}
            );
            const center = m.getCenter();
            setMapPreferences({
                view: {
                    center: [round(center.lng, 5), round(center.lat, 5)],
                    zoom: round(zoom, 2),
                },
            });
        };
        m.on('load', onMoveEnd);
        m.on('moveend', onMoveEnd);

        // The map only follows the window size by itself
        const resizeObserver = new ResizeObserver(() => m.resize());
        resizeObserver.observe(container);

        setMap(m);

        return () => {
            resizeObserver.disconnect();
            m.remove();
            setMap(null);
        };
        // The map is created once: the style and the language change on it
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [token]);

    const appliedStyle = useRef(styleUrl);
    useEffect(() => {
        if (map && appliedStyle.current !== styleUrl) {
            appliedStyle.current = styleUrl;
            // A diffed style change emits no `style.load`: the result layers
            // would be dropped without being added back
            map.setStyle(styleUrl, {
                diff: false,
                // Required by the typings, the map defaults when undefined
                localFontFamily: undefined,
                localIdeographFontFamily: undefined,
            });
        }
    }, [map, styleUrl]);

    useEffect(() => {
        map?.setLanguage(i18n.language);
    }, [map, i18n.language]);

    // Clusters ---------------------------------------------------------------

    // Sorting changes no position: it is left out of the key
    const clusterSearch = geoClusterSearch(results.searchOptions);
    const clusters = useQuery({
        queryKey: [
            'geo-clusters',
            clusterSearch,
            search.reloadInc,
            field.searchSlug,
            viewport,
        ],
        queryFn: ({signal}) =>
            getGeoClusters(
                clusterSearch,
                {
                    field: field.searchSlug,
                    precision: viewport!.precision,
                    bounds: viewport!.bounds,
                },
                signal
            ),
        enabled: !!viewport,
        placeholderData: keepPreviousData,
    });
    const data = clusters.data;

    const {bubbles, pins} = useMemo(() => {
        const all = data?.clusters ?? [];
        const singles = all.filter(c => c.assetId);
        if (singles.length > MAX_PINS) {
            return {bubbles: all, pins: []};
        }

        return {bubbles: all.filter(c => !c.assetId), pins: singles};
    }, [data]);

    const fitBounds = useCallback(
        (b: GeoBounds, animate = true) => {
            map?.fitBounds(
                [
                    [b.left, b.bottom],
                    // A box across the antimeridian goes on eastwards
                    [b.right < b.left ? b.right + 360 : b.right, b.top],
                ],
                {padding: 60, maxZoom: 15, animate}
            );
        },
        [map]
    );

    useEffect(() => {
        if (!framed.current && data?.bounds) {
            framed.current = true;
            fitBounds(data.bounds, false);
        }
    }, [data, fitBounds]);

    useEffect(() => {
        map?.getSource<GeoJSONSource>('clusters')?.setData({
            type: 'FeatureCollection',
            features: bubbles.map(c => ({
                type: 'Feature',
                geometry: {type: 'Point', coordinates: [c.lng, c.lat]},
                properties: {
                    key: c.key,
                    count: c.count,
                    label: compactCount(c.count, i18n.language),
                },
            })),
        });
    }, [map, styleGeneration, bubbles, i18n.language]);

    // Single assets ----------------------------------------------------------

    const pinIds = useMemo(() => pins.map(c => c.assetId!), [pins]);
    const pinAssets = useAssetStore(
        useShallow(s => pinIds.map(id => s.assets[id]))
    );
    const missingIds = useMemo(
        () => pinIds.filter((_id, i) => !pinAssets[i]).sort(),
        [pinIds, pinAssets]
    );
    useQuery({
        queryKey: ['map-pin-assets', results.searchOptions, missingIds],
        queryFn: async ({signal}) => {
            // The search options keep the deleted or quarantined assets the search shows
            const result = await searchAssets(
                {
                    ...results.searchOptions,
                    ids: missingIds,
                    limit: missingIds.length,
                },
                signal
            );
            storeAssets(result.items);

            return result.items.length;
        },
        enabled: missingIds.length > 0,
        staleTime: Infinity,
    });
    const assetsById = useMemo(() => {
        const index: Record<string, Asset> = {};
        pinAssets.forEach(a => a && (index[a.id] = a));

        return index;
    }, [pinAssets]);

    const markers = useRef(
        new Map<string, {marker: Marker; node: HTMLElement}>()
    );
    const [pinNodes, setPinNodes] = useState<
        {cluster: GeoCluster; node: HTMLElement}[]
    >([]);
    useEffect(() => {
        const current = markers.current;

        // The markers belong to a map: a new one starts without any
        return () => {
            current.forEach(m => m.marker.remove());
            current.clear();
        };
    }, [map]);
    useEffect(() => {
        if (!map) {
            return;
        }
        const current = markers.current;
        const wanted = new Set(pinIds);
        current.forEach((m, id) => {
            if (!wanted.has(id)) {
                m.marker.remove();
                current.delete(id);
            }
        });
        setPinNodes(
            pins.map(c => {
                let m = current.get(c.assetId!);
                if (m) {
                    // A kept marker may move: other geo field, edited position
                    m.marker.setLngLat([c.lng, c.lat]);
                } else {
                    const node = document.createElement('div');
                    m = {
                        node,
                        marker: new Marker({element: node})
                            .setLngLat([c.lng, c.lat])
                            .addTo(map),
                    };
                    current.set(c.assetId!, m);
                }

                return {cluster: c, node: m.node};
            })
        );
    }, [map, pins, pinIds]);

    // Zones ------------------------------------------------------------------

    const zonesConditionId = mapZonesConditionId(field.slug);
    const zonesCondition = search.conditions.find(
        c => c.id === zonesConditionId
    );
    const zonesQuery = zonesCondition?.query;
    const circles = useMemo(
        () => (zonesQuery ? (parseCircles(zonesQuery, field.slug) ?? []) : []),
        [zonesQuery, field.slug]
    );
    const zonesDisabled = !!zonesCondition?.disabled;

    const setZones = useCallback(
        (next: GeoCircle[]) => {
            if (next.length === 0) {
                search.removeCondition(zonesConditionId);
            } else {
                search.upsertCondition({
                    id: zonesConditionId,
                    query: circlesToAQL(field.slug, next),
                });
            }
        },
        [search, zonesConditionId, field.slug]
    );

    const shownCircles = useMemo(
        () =>
            zoneEdit?.base === circles
                ? circles.map((c, i) =>
                      i === zoneEdit.index ? zoneEdit.circle : c
                  )
                : circles,
        [circles, zoneEdit]
    );
    const editedCircle =
        zoneEdit?.base === circles && zoneEdit.active
            ? zoneEdit.circle
            : undefined;

    useEffect(() => {
        const features = shownCircles.map((c, index) =>
            circleFeature(c, {index, draft: false, disabled: zonesDisabled})
        );
        if (draft && draft.radius > 0) {
            features.push(
                circleFeature(draft, {index: -1, draft: true, disabled: false})
            );
        }
        map?.getSource<GeoJSONSource>('zones')?.setData({
            type: 'FeatureCollection',
            features,
        });
    }, [map, styleGeneration, shownCircles, draft, zonesDisabled]);

    const onZoneDrawn = useEffectEvent((circle: GeoCircle) => {
        setZones([...circles, circle]);
    });

    useEffect(() => {
        if (!map || !drawing) {
            return;
        }
        const canvas = map.getCanvasContainer();
        canvas.style.cursor = 'crosshair';
        map.dragPan.disable();
        map.doubleClickZoom.disable();
        map.touchZoomRotate.disable();
        setPopup(null);

        let center: LngLat | null = null;
        let start: Point | null = null;
        let last: {lngLat: LngLat; point: Point} | null = null;

        const onDown = (e: MapMouseEvent | MapTouchEvent) => {
            if ('points' in e && e.points.length > 1) {
                return;
            }
            e.preventDefault();
            center = {lat: e.lngLat.lat, lng: e.lngLat.lng};
            start = e.point;
            last = null;
            setDraft({...center, radius: 0});
        };
        const onMove = (e: MapMouseEvent | MapTouchEvent) => {
            if (!center) {
                return;
            }
            last = {lngLat: e.lngLat, point: e.point};
            setDraft({...center, radius: distance(center, e.lngLat)});
        };
        const finish = () => {
            if (!center) {
                return;
            }
            const c = center;
            center = null;
            setDraft(null);
            if (last && start && start.dist(last.point) >= MIN_ZONE_PIXELS) {
                onZoneDrawn({
                    lat: round(c.lat),
                    lng: round(c.lng),
                    radius: Math.round(distance(c, last.lngLat)),
                });
                setDrawing(false);
            }
        };
        const cancel = () => {
            center = null;
            setDraft(null);
        };
        const onKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                cancel();
                setDrawing(false);
            }
        };

        map.on('mousedown', onDown);
        map.on('touchstart', onDown);
        map.on('mousemove', onMove);
        map.on('touchmove', onMove);
        map.on('touchend', finish);
        map.on('touchcancel', cancel);
        // Released outside of the map too
        window.addEventListener('mouseup', finish);
        window.addEventListener('keydown', onKeyDown);

        return () => {
            map.off('mousedown', onDown);
            map.off('touchstart', onDown);
            map.off('mousemove', onMove);
            map.off('touchmove', onMove);
            map.off('touchend', finish);
            map.off('touchcancel', cancel);
            window.removeEventListener('mouseup', finish);
            window.removeEventListener('keydown', onKeyDown);
            canvas.style.cursor = '';
            map.dragPan.enable();
            map.doubleClickZoom.enable();
            map.touchZoomRotate.enable();
            setDraft(null);
        };
    }, [map, drawing]);

    const getCircles = useEffectEvent(() => circles);
    const onZoneEdited = useEffectEvent((index: number, circle: GeoCircle) => {
        setZoneEdit({base: circles, index, circle, active: false});
        setZones(circles.map((c, i) => (i === index ? circle : c)));
    });

    // Zones are moved by their inside and resized by their outline
    useEffect(() => {
        if (!map || drawing) {
            return;
        }
        const canvas = map.getCanvasContainer();

        let grab: {
            index: number;
            part: 'edge' | 'inside';
            origin: GeoCircle;
            from: LngLat;
            start: Point;
            moved: boolean;
        } | null = null;
        let edited: GeoCircle | null = null;

        const hit = (e: MapMouseEvent | MapTouchEvent) => {
            // Bubbles and thumbnails are above the zones
            const target = e.originalEvent.target;
            if (
                (target instanceof Element &&
                    target.closest('.mapboxgl-marker')) ||
                map.queryRenderedFeatures(e.point, {layers: ['clusters']})
                    .length > 0
            ) {
                return undefined;
            }
            const pixels =
                'points' in e ? EDGE_PIXELS.touch : EDGE_PIXELS.mouse;
            const tolerance = distance(
                e.lngLat,
                map.unproject([e.point.x + pixels, e.point.y])
            );

            return hitZone(getCircles(), e.lngLat, tolerance);
        };

        const hoverCursor = (e: MapMouseEvent) => {
            if (
                map.queryRenderedFeatures(e.point, {layers: ['clusters']})
                    .length > 0
            ) {
                return 'pointer';
            }
            const zone = hit(e);
            if (!zone) {
                return '';
            }
            if (zone.part === 'inside') {
                return 'move';
            }
            const c = map.project([
                getCircles()[zone.index].lng,
                getCircles()[zone.index].lat,
            ]);

            return resizeCursor(e.point.x - c.x, e.point.y - c.y);
        };

        const onDown = (e: MapMouseEvent | MapTouchEvent) => {
            if ('points' in e && e.points.length > 1) {
                return;
            }
            const zone = hit(e);
            const origin = zone && getCircles()[zone.index];
            if (!zone || !origin) {
                return;
            }
            // Keeps the map from panning
            e.preventDefault();
            grab = {
                ...zone,
                origin,
                from: {lat: e.lngLat.lat, lng: e.lngLat.lng},
                start: e.point,
                moved: false,
            };
        };
        const onMove = (e: MapMouseEvent | MapTouchEvent) => {
            if (!grab) {
                if (e.type === 'mousemove') {
                    canvas.style.cursor = hoverCursor(e as MapMouseEvent);
                }

                return;
            }
            if (!grab.moved) {
                if (grab.start.dist(e.point) < CLICK_PIXELS) {
                    return;
                }
                grab.moved = true;
                setPopup(null);
            }
            edited =
                grab.part === 'inside'
                    ? moveCircle(grab.origin, grab.from, e.lngLat)
                    : {
                          ...grab.origin,
                          radius: Math.max(1, distance(grab.origin, e.lngLat)),
                      };
            setZoneEdit({
                base: getCircles(),
                index: grab.index,
                circle: edited,
                active: true,
            });
        };
        const finish = () => {
            if (!grab) {
                return;
            }
            const {index, moved} = grab;
            grab = null;
            if (moved && edited) {
                onZoneEdited(index, {
                    lat: round(edited.lat),
                    lng: round(edited.lng),
                    radius: Math.round(edited.radius),
                });
            }
            edited = null;
        };
        // The grab would otherwise outlive the gesture: the next pan would move the zone
        const cancel = () => {
            if (grab) {
                grab = null;
                edited = null;
                setZoneEdit(null);
            }
        };
        const onKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                cancel();
            }
        };

        map.on('mousedown', onDown);
        map.on('touchstart', onDown);
        map.on('mousemove', onMove);
        map.on('touchmove', onMove);
        map.on('touchend', finish);
        map.on('touchcancel', cancel);
        // Released outside of the map too
        window.addEventListener('mouseup', finish);
        window.addEventListener('keydown', onKeyDown);

        return () => {
            map.off('mousedown', onDown);
            map.off('touchstart', onDown);
            map.off('mousemove', onMove);
            map.off('touchmove', onMove);
            map.off('touchend', finish);
            map.off('touchcancel', cancel);
            window.removeEventListener('mouseup', finish);
            window.removeEventListener('keydown', onKeyDown);
            canvas.style.cursor = '';
        };
    }, [map, drawing]);

    // Interactions -----------------------------------------------------------

    const clustersByKey = useMemo(
        () => new Map(bubbles.map(c => [c.key, c])),
        [bubbles]
    );
    const openFromMap = useCallback(
        (asset: Asset, siblings: string[]) => openAsset(asset, {siblings}),
        [openAsset]
    );

    /** A single asset drawn as a bubble (too many to be thumbnails) */
    const openAssetById = useCallback(
        async (id: string) => {
            const asset =
                useAssetStore.getState().assets[id] ??
                (await getAsset(id).catch(() => undefined));
            if (asset) {
                storeAssets([asset]);
                openFromMap(asset, [id]);
            }
        },
        [openFromMap, storeAssets]
    );

    const onMapClick = useEffectEvent((e: MapMouseEvent) => {
        // A click on a thumbnail marker reaches the map too
        const target = e.originalEvent.target;
        if (
            drawing ||
            !map ||
            (target instanceof Element && target.closest('.mapboxgl-marker'))
        ) {
            return;
        }
        const features = map.queryRenderedFeatures(e.point, {
            layers: ['clusters', 'zones-fill'],
        });
        const bubble = features.find(f => f.layer?.id === 'clusters');
        const cluster = bubble && clustersByKey.get(bubble.properties?.key);
        if (cluster) {
            if (cluster.assetId) {
                void openAssetById(cluster.assetId);
            } else {
                setPopup({kind: 'cluster', cluster});
            }

            return;
        }
        const zone = features.find(
            f => f.layer?.id === 'zones-fill' && !f.properties?.draft
        );
        if (zone) {
            setPopup({
                kind: 'zone',
                index: zone.properties!.index as number,
                at: {lat: e.lngLat.lat, lng: e.lngLat.lng},
            });
        }
    });

    useEffect(() => {
        if (!map) {
            return;
        }
        const onClick = (e: MapMouseEvent) => onMapClick(e);
        // A cluster is only one at its zoom level
        const onZoom = () =>
            setPopup(cur => (cur?.kind === 'cluster' ? null : cur));
        map.on('click', onClick);
        map.on('zoomstart', onZoom);

        return () => {
            map.off('click', onClick);
            map.off('zoomstart', onZoom);
        };
    }, [map]);

    useEffect(() => {
        if (!map || !popup) {
            return;
        }
        const at =
            popup.kind === 'zone'
                ? popup.at
                : {lat: popup.cluster.lat, lng: popup.cluster.lng};
        const p = new Popup({
            maxWidth: 'none',
            offset: 20,
            focusAfterOpen: false,
        })
            .setLngLat([at.lng, at.lat])
            .setDOMContent(popupNode)
            .addTo(map);
        p.on('close', () => setPopup(cur => (cur === popup ? null : cur)));

        return () => {
            p.remove();
        };
    }, [map, popup, popupNode]);

    const searchArea = (cluster: GeoCluster) => {
        setPopup(null);
        fitBounds(padBounds(cluster.bounds, 100));
        search.upsertCondition({
            id: MAP_AREA_CONDITION_ID,
            query: clusterCondition(field.slug, cluster),
        });
    };

    const zoomToCircle = (c: GeoCircle) => {
        const north = c.lat + c.radius / 111320;
        const south = c.lat - c.radius / 111320;
        const dLng =
            c.radius /
            111320 /
            Math.max(0.01, Math.cos((c.lat * Math.PI) / 180));
        fitBounds({
            top: north,
            bottom: south,
            left: c.lng - dLng,
            right: c.lng + dLng,
        });
    };

    const renderPopup = () => {
        if (!popup) {
            return null;
        }
        switch (popup.kind) {
            case 'cluster':
                return (
                    <ClusterPopup
                        cluster={popup.cluster}
                        field={field.slug}
                        searchOptions={results.searchOptions}
                        onZoom={() => {
                            setPopup(null);
                            fitBounds(padBounds(popup.cluster.bounds, 100));
                        }}
                        onSearch={() => searchArea(popup.cluster)}
                        onOpen={openFromMap}
                    />
                );
            case 'zone': {
                const circle = circles[popup.index];
                if (!circle) {
                    return null;
                }

                return (
                    <ZonePopup
                        circle={circle}
                        onZoom={() => {
                            setPopup(null);
                            zoomToCircle(circle);
                        }}
                        onRemove={() => {
                            setPopup(null);
                            setZones(
                                circles.filter((_c, i) => i !== popup.index)
                            );
                        }}
                    />
                );
            }
        }
    };

    const hasArea = search.conditions.some(c => c.id === MAP_AREA_CONDITION_ID);

    return (
        <div
            data-testid="asset-map"
            className="dbx-map relative h-full w-full overflow-hidden bg-muted"
        >
            {/* Inside the map element: the hover preview shows in fullscreen too */}
            <PreviewProvider>
                {/* Sized, not positioned: the Mapbox stylesheet makes its
            container relative */}
                <div ref={containerRef} className="h-full w-full" />

                <div className="absolute top-3 left-3 z-10 flex max-w-[calc(100%-5rem)] flex-wrap items-center gap-1 rounded-lg border bg-background/95 p-1 shadow-md backdrop-blur">
                    {fields.length > 1 ? (
                        <SimpleSelect
                            size="sm"
                            className="w-44"
                            value={field.searchSlug}
                            onValueChange={v => setMapPreferences({field: v})}
                            options={fields.map(f => ({
                                value: f.searchSlug,
                                label: f.label,
                            }))}
                        />
                    ) : null}
                    <Tooltip
                        content={t(
                            'map.draw_zone_help',
                            'Click and drag on the map to search within a circle'
                        )}
                    >
                        <Toggle
                            size="sm"
                            data-testid="map-draw-zone"
                            pressed={drawing}
                            onPressedChange={setDrawing}
                        >
                            <CircleDashedIcon />{' '}
                            {t('map.draw_zone', 'Draw a zone')}
                        </Toggle>
                    </Tooltip>
                    {circles.length > 0 ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            data-testid="map-clear-zones"
                            onClick={() => setZones([])}
                        >
                            <XIcon />
                            {t('map.clear_zones', 'Clear zones ({{count}})', {
                                count: circles.length,
                            })}
                        </Button>
                    ) : null}
                    {hasArea ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                search.removeCondition(MAP_AREA_CONDITION_ID)
                            }
                        >
                            <XIcon /> {t('map.clear_area', 'Clear area')}
                        </Button>
                    ) : null}
                    <Separator orientation="vertical" className="mx-0.5 h-5" />
                    <Tooltip
                        content={t('map.fit_results', 'Show all the results')}
                    >
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            data-testid="map-fit"
                            aria-label={t(
                                'map.fit_results',
                                'Show all the results'
                            )}
                            disabled={!data?.bounds}
                            onClick={() =>
                                data?.bounds && fitBounds(data.bounds)
                            }
                        >
                            <ScanSearchIcon />
                        </Button>
                    </Tooltip>
                    <Tooltip content={t('map.satellite', 'Satellite view')}>
                        <Toggle
                            size="sm"
                            aria-label={t('map.satellite', 'Satellite view')}
                            pressed={mapStyle === 'satellite'}
                            onPressedChange={p =>
                                setMapPreferences({
                                    style: p ? 'satellite' : 'streets',
                                })
                            }
                        >
                            <SatelliteIcon />
                        </Toggle>
                    </Tooltip>
                </div>

                <div className="pointer-events-none absolute bottom-8 left-1/2 z-10 flex -translate-x-1/2 flex-col items-center gap-2">
                    {editedCircle && !drawing ? (
                        <div
                            data-testid="map-zone-edit-info"
                            className="flex gap-3 rounded-full bg-foreground px-3 py-1.5 text-xs text-background tabular-nums shadow-md"
                        >
                            <span>
                                {t('map.zone_center', 'Center: {{center}}', {
                                    center: formatLatLng(editedCircle),
                                })}
                            </span>
                            <span>
                                {t('map.zone_radius', 'Radius: {{radius}}', {
                                    radius: formatDistance(
                                        editedCircle.radius,
                                        i18n.language
                                    ),
                                })}
                            </span>
                        </div>
                    ) : null}
                    {drawing ? (
                        <div className="rounded-full bg-foreground px-3 py-1.5 text-xs text-background shadow-md">
                            {draft && draft.radius > 0
                                ? t('map.zone_radius', 'Radius: {{radius}}', {
                                      radius: formatDistance(
                                          draft.radius,
                                          i18n.language
                                      ),
                                  })
                                : t(
                                      'map.draw_zone_hint',
                                      'Click and drag to draw a circle — Esc to cancel'
                                  )}
                        </div>
                    ) : null}
                    {data ? (
                        <div
                            data-testid="map-stats"
                            className={cn(
                                'flex items-center gap-2 rounded-full border bg-background/95 px-3 py-1 text-xs text-muted-foreground shadow-sm backdrop-blur',
                                data.geolocated === 0 && 'text-foreground'
                            )}
                        >
                            {clusters.isFetching ? (
                                <Spinner className="size-3" />
                            ) : null}
                            {data.geolocated === 0
                                ? t(
                                      'map.no_position',
                                      'None of the {{total}} results has a position',
                                      {
                                          count: data.total,
                                          total: formatNumber(
                                              data.total,
                                              i18n.language
                                          ),
                                      }
                                  )
                                : t(
                                      'map.geolocated',
                                      '{{geolocated}} of {{total}} results on the map',
                                      {
                                          count: data.total,
                                          geolocated: formatNumber(
                                              data.geolocated,
                                              i18n.language
                                          ),
                                          total: formatNumber(
                                              data.total,
                                              i18n.language
                                          ),
                                      }
                                  )}
                        </div>
                    ) : null}
                    {clusters.isError ? (
                        <div className="rounded-full bg-destructive px-3 py-1 text-xs text-destructive-foreground shadow-sm">
                            {t('map.error', 'Could not load the positions')}
                        </div>
                    ) : null}
                </div>

                {pinNodes.map(({cluster, node}) =>
                    createPortal(
                        <AssetPin
                            asset={assetsById[cluster.assetId!]}
                            onClick={() => {
                                const asset = assetsById[cluster.assetId!];
                                if (asset && !drawing) {
                                    openFromMap(asset, pinIds);
                                }
                            }}
                        />,
                        node,
                        cluster.assetId!
                    )
                )}
                {popup ? createPortal(renderPopup(), popupNode) : null}
            </PreviewProvider>
        </div>
    );
}

/** The resize cursor along the direction from the center of a zone */
function resizeCursor(dx: number, dy: number): string {
    const angle = ((Math.atan2(dy, dx) * 180) / Math.PI + 180) % 180;
    if (angle < 22.5 || angle >= 157.5) {
        return 'ew-resize';
    }
    if (angle < 67.5) {
        return 'nwse-resize';
    }
    if (angle < 112.5) {
        return 'ns-resize';
    }

    return 'nesw-resize';
}

function circleFeature(
    circle: GeoCircle,
    properties: {index: number; draft: boolean; disabled: boolean}
): Feature {
    return {
        type: 'Feature',
        geometry: {type: 'Polygon', coordinates: [circleRing(circle)]},
        properties,
    };
}

/** Sources and layers of the results: a style change drops them */
function addLayers(m: MapboxMap, colors: MapColors) {
    if (m.getSource('zones')) {
        return;
    }
    const empty: FeatureCollection = {type: 'FeatureCollection', features: []};

    m.addSource('zones', {type: 'geojson', data: empty});
    m.addLayer({
        id: 'zones-fill',
        type: 'fill',
        source: 'zones',
        paint: {
            'fill-color': colors.primary,
            'fill-opacity': [
                'case',
                ['get', 'draft'],
                0.1,
                ['get', 'disabled'],
                0.04,
                0.14,
            ],
        },
    });
    m.addLayer({
        id: 'zones-line',
        type: 'line',
        source: 'zones',
        filter: ['!', ['get', 'draft']],
        paint: {
            'line-color': colors.primary,
            'line-width': 2,
            'line-opacity': ['case', ['get', 'disabled'], 0.4, 1],
        },
    });
    m.addLayer({
        id: 'zones-draft-line',
        type: 'line',
        source: 'zones',
        filter: ['get', 'draft'],
        paint: {
            'line-color': colors.primary,
            'line-width': 2,
            'line-dasharray': [2, 2],
        },
    });

    m.addSource('clusters', {type: 'geojson', data: empty});
    m.addLayer({
        id: 'clusters',
        type: 'circle',
        source: 'clusters',
        paint: {
            'circle-color': colors.primary,
            'circle-opacity': 0.9,
            'circle-radius': [
                'step',
                ['get', 'count'],
                14,
                10,
                18,
                100,
                23,
                1000,
                28,
                10000,
                34,
            ],
            'circle-stroke-width': 3,
            'circle-stroke-color': colors.background,
            'circle-stroke-opacity': 0.8,
        },
    });
    m.addLayer({
        id: 'cluster-count',
        type: 'symbol',
        source: 'clusters',
        layout: {
            'text-field': ['get', 'label'],
            'text-size': 12,
            'text-font': ['DIN Pro Bold', 'Arial Unicode MS Bold'],
            'text-allow-overlap': true,
            'text-ignore-placement': true,
        },
        paint: {
            'text-color': colors.primaryForeground,
        },
    });
}
