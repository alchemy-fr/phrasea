import type {GeoBounds} from '@/lib/api/assets';
import {parseAQL} from '@/features/search/aql/parser';
import {
    AQLCondition,
    AQLExpression,
    AQLLogical,
    AQLOperator,
    AQLValueExpr,
    isCondition,
    isField,
    isLiteral,
    isLogical,
} from '@/features/search/aql/types';

/**
 * Condition holding the zones drawn on the map (circles, OR-ed), one per geo
 * attribute: switching the attribute of the map keeps the zones of the others.
 */
export function mapZonesConditionId(field: string): string {
    return `map-zones-${field}`;
}
/** Condition restricting the search to the area of a cluster */
export const MAP_AREA_CONDITION_ID = 'map-area';

export type LngLat = {lat: number; lng: number};

export type GeoCircle = LngLat & {
    /** Meters */
    radius: number;
};

const EARTH_RADIUS = 6371008.8;
/** Web Mercator stops there: tiles do not cover the poles */
const MAX_LATITUDE = 85.05112878;

/**
 * Tile zoom level of the clusters for a map zoom: map tiles are 512px wide,
 * so precision `zoom + 2` makes cells of 128px, about two cluster bubbles.
 */
export function tilePrecision(zoom: number): number {
    return Math.max(0, Math.min(29, Math.floor(zoom) + 2));
}

/**
 * The box to cluster: the visible area grown by `padding` (a ratio of its
 * size) on every side, so that small moves need no new request. Undefined when
 * the whole world width is covered.
 */
export function clusterBounds(
    {
        west,
        south,
        east,
        north,
    }: {west: number; south: number; east: number; north: number},
    padding = 0.5
): GeoBounds | undefined {
    const dLng = (east - west) * padding;
    const dLat = (north - south) * padding;
    const left = west - dLng;
    const right = east + dLng;
    if (right - left >= 360) {
        return undefined;
    }

    return {
        top: round(Math.min(MAX_LATITUDE, north + dLat), 4),
        bottom: round(Math.max(-MAX_LATITUDE, south - dLat), 4),
        left: round(wrapLongitude(left), 4),
        right: round(wrapLongitude(right), 4),
    };
}

/**
 * Whether the clustered box still holds the visible area. A box crossing the
 * antimeridian, or a view beyond it, is never trusted: the map refetches.
 */
export function coversArea(
    bounds: GeoBounds | undefined,
    {
        west,
        south,
        east,
        north,
    }: {west: number; south: number; east: number; north: number}
): boolean {
    if (!bounds) {
        return true;
    }
    if (bounds.left > bounds.right || west < -180 || east > 180) {
        return false;
    }

    return (
        bounds.left <= west &&
        bounds.right >= east &&
        bounds.top >= north &&
        bounds.bottom <= south
    );
}

/** A longitude brought back into [-180, 180] (the map wraps around the world) */
export function wrapLongitude(lng: number): number {
    const w = ((((lng + 180) % 360) + 360) % 360) - 180;

    return w === -180 && lng > 0 ? 180 : w;
}

export function round(value: number, decimals = 6): number {
    const f = 10 ** decimals;

    return Math.round(value * f) / f;
}

/** Great-circle distance in meters */
export function distance(a: LngLat, b: LngLat): number {
    const toRad = Math.PI / 180;
    const dLat = (b.lat - a.lat) * toRad;
    const dLng = (b.lng - a.lng) * toRad;
    const h =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(a.lat * toRad) *
            Math.cos(b.lat * toRad) *
            Math.sin(dLng / 2) ** 2;

    return 2 * EARTH_RADIUS * Math.asin(Math.min(1, Math.sqrt(h)));
}

/** The point at `meters` from `origin` in the `bearing` direction (radians) */
export function destination(
    origin: LngLat,
    meters: number,
    bearing: number
): LngLat {
    const toRad = Math.PI / 180;
    const d = meters / EARTH_RADIUS;
    const lat1 = origin.lat * toRad;
    const lng1 = origin.lng * toRad;
    const lat2 = Math.asin(
        Math.sin(lat1) * Math.cos(d) +
            Math.cos(lat1) * Math.sin(d) * Math.cos(bearing)
    );
    const lng2 =
        lng1 +
        Math.atan2(
            Math.sin(bearing) * Math.sin(d) * Math.cos(lat1),
            Math.cos(d) - Math.sin(lat1) * Math.sin(lat2)
        );

    return {lat: lat2 / toRad, lng: lng2 / toRad};
}

export type ZoneHit = {index: number; part: 'edge' | 'inside'};

/**
 * The zone a press at `at` grabs: the outline nearest within `tolerance`
 * meters (to resize it), else the smallest zone containing the point (to move
 * it), so that a zone drawn inside a larger one can still be grabbed.
 */
export function hitZone(
    circles: GeoCircle[],
    at: LngLat,
    tolerance: number
): ZoneHit | undefined {
    let edge: {index: number; gap: number} | undefined;
    let inside: {index: number; radius: number} | undefined;
    circles.forEach((c, index) => {
        const d = distance(c, at);
        const gap = Math.abs(d - c.radius);
        if (gap <= tolerance && (!edge || gap < edge.gap)) {
            edge = {index, gap};
        }
        if (d < c.radius && (!inside || c.radius < inside.radius)) {
            inside = {index, radius: c.radius};
        }
    });
    if (edge) {
        return {index: edge.index, part: 'edge'};
    }

    return inside ? {index: inside.index, part: 'inside'} : undefined;
}

/** The circle moved by the drag from `from` to `to`, its radius kept */
export function moveCircle(
    circle: GeoCircle,
    from: LngLat,
    to: LngLat
): GeoCircle {
    return {
        lat: Math.max(
            -MAX_LATITUDE,
            Math.min(MAX_LATITUDE, circle.lat + to.lat - from.lat)
        ),
        lng: wrapLongitude(circle.lng + to.lng - from.lng),
        radius: circle.radius,
    };
}

/**
 * The ring of a circle on the ground as GeoJSON `[lng, lat]` positions: a
 * circle on the Earth is not a circle on the Mercator projection.
 */
export function circleRing(circle: GeoCircle, steps = 72): [number, number][] {
    const ring: [number, number][] = [];
    for (let i = 0; i <= steps; i++) {
        const p = destination(circle, circle.radius, (i / steps) * 2 * Math.PI);
        ring.push([p.lng, p.lat]);
    }

    return ring;
}

export function formatDistance(meters: number, lang?: string): string {
    if (meters < 1000) {
        return `${new Intl.NumberFormat(lang, {maximumFractionDigits: 0}).format(meters)} m`;
    }

    return `${new Intl.NumberFormat(lang, {
        maximumFractionDigits: meters < 10000 ? 1 : 0,
    }).format(meters / 1000)} km`;
}

export function circleToAQL(field: string, c: GeoCircle): string {
    return `${field} WITHIN CIRCLE (${round(c.lat)}, ${round(c.lng)}, "${Math.round(c.radius)}m")`;
}

/** Assets in any of the circles */
export function circlesToAQL(field: string, circles: GeoCircle[]): string {
    return circles.map(c => circleToAQL(field, c)).join(' OR ');
}

export function boundsToAQL(field: string, b: GeoBounds): string {
    return `${field} WITHIN RECTANGLE (${round(b.top)}, ${round(b.left)}, ${round(b.bottom)}, ${round(b.right)})`;
}

/**
 * A box at least `minMeters` wide around the given one: the points of a
 * cluster may share the very same position, the box must still have an area.
 */
export function padBounds(b: GeoBounds, minMeters = 10): GeoBounds {
    const dLat = minMeters / 111320 / 2;
    const midLat = ((b.top + b.bottom) / 2) * (Math.PI / 180);
    const dLng = dLat / Math.max(0.01, Math.cos(midLat));

    return {
        top: Math.min(90, b.top + dLat),
        bottom: Math.max(-90, b.bottom - dLat),
        left: Math.max(-180, b.left - dLng),
        right: Math.min(180, b.right + dLng),
    };
}

/**
 * The box around a circle, kept within the Mercator latitudes: a large
 * circle reaches beyond the poles, which the map cannot frame.
 */
export function circleBounds(c: GeoCircle): GeoBounds {
    const dLat = c.radius / 111320;
    const dLng = dLat / Math.max(0.01, Math.cos((c.lat * Math.PI) / 180));

    return {
        top: Math.min(MAX_LATITUDE, c.lat + dLat),
        bottom: Math.max(-MAX_LATITUDE, c.lat - dLat),
        left: c.lng - dLng,
        right: c.lng + dLng,
    };
}

/** Meters of an AQL distance: a number of meters or an ES distance ("12km") */
function parseRadius(value: AQLValueExpr | undefined): number | undefined {
    if (typeof value === 'number') {
        return value;
    }
    if (!isLiteral(value)) {
        return undefined;
    }
    const m = /^\s*([\d.]+)\s*(m|km|mi|yd|ft|meters?|kilometers?)?\s*$/i.exec(
        value.literal
    );
    if (!m) {
        return undefined;
    }
    const n = parseFloat(m[1]);
    const unit = (m[2] ?? 'm').toLowerCase();
    const factors: Record<string, number> = {
        m: 1,
        meter: 1,
        meters: 1,
        km: 1000,
        kilometer: 1000,
        kilometers: 1000,
        mi: 1609.344,
        yd: 0.9144,
        ft: 0.3048,
    };

    return n * factors[unit];
}

/**
 * The circles of a zones condition (`a WITHIN CIRCLE (…) OR a WITHIN CIRCLE
 * (…)`) on the given field. Undefined when the condition is anything else:
 * edited by hand into something the map cannot show.
 */
export function parseCircles(
    query: string,
    field: string
): GeoCircle[] | undefined {
    const ast = parseAQL(query);
    if (!ast) {
        return undefined;
    }

    const circles: GeoCircle[] = [];
    const visit = (e: AQLExpression): boolean => {
        if (isLogical(e)) {
            return e.operator === AQLLogical.OR && e.conditions.every(visit);
        }
        if (!isCondition(e) || e.operator !== AQLOperator.WITHIN_CIRCLE) {
            return false;
        }
        if (!isField(e.leftOperand) || e.leftOperand.field !== field) {
            return false;
        }
        const circle = conditionCircle(e);
        if (!circle) {
            return false;
        }
        circles.push(circle);

        return true;
    };

    return visit(ast.expression) ? circles : undefined;
}

/** The circle of a `WITHIN CIRCLE` condition, unless its arguments are not plain values */
export function conditionCircle(
    condition: AQLCondition
): GeoCircle | undefined {
    if (
        condition.operator !== AQLOperator.WITHIN_CIRCLE ||
        !Array.isArray(condition.rightOperand)
    ) {
        return undefined;
    }
    const [lat, lng, r] = condition.rightOperand;
    const radius = parseRadius(r);
    if (typeof lat !== 'number' || typeof lng !== 'number' || !radius) {
        return undefined;
    }

    return {lat, lng, radius};
}

/** The bounds of a `WITHIN RECTANGLE` condition, unless its arguments are not numbers */
export function conditionBounds(
    condition: AQLCondition
): GeoBounds | undefined {
    if (
        condition.operator !== AQLOperator.WITHIN_RECTANGLE ||
        !Array.isArray(condition.rightOperand)
    ) {
        return undefined;
    }
    const [top, left, bottom, right] = condition.rightOperand;
    if (
        typeof top !== 'number' ||
        typeof left !== 'number' ||
        typeof bottom !== 'number' ||
        typeof right !== 'number'
    ) {
        return undefined;
    }

    return {top, left, bottom, right};
}

/**
 * "48.8566, 2.3522": coordinates rounded to about 10 m, with a decimal point
 * whatever the language, as a decimal comma would mix with the separator.
 */
export function formatLatLng(point: LngLat): string {
    return `${round(point.lat, 4)}, ${round(point.lng, 4)}`;
}

/** Bubble label: 1234 → "1.2K" */
export function compactCount(count: number, lang?: string): string {
    return new Intl.NumberFormat(lang, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(count);
}
