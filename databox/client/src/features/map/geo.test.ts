import {describe, expect, it} from 'vitest';
import {
    boundsToAQL,
    circleRing,
    circlesToAQL,
    clusterBounds,
    conditionBounds,
    conditionCircle,
    coversArea,
    destination,
    distance,
    formatDistance,
    formatLatLng,
    hitZone,
    moveCircle,
    padBounds,
    parseCircles,
    tilePrecision,
    wrapLongitude,
} from './geo';
import {parseAQL} from '@/features/search/aql/parser';
import {AQLCondition} from '@/features/search/aql/types';

function condition(query: string): AQLCondition {
    return parseAQL(query)!.expression as AQLCondition;
}

describe('map geo helpers', () => {
    it('derives the cluster precision from the zoom', () => {
        expect(tilePrecision(0)).toBe(2);
        expect(tilePrecision(5.7)).toBe(7);
        expect(tilePrecision(40)).toBe(29);
    });

    it('pads the visible area and drops it when it spans the world', () => {
        expect(
            clusterBounds({west: 0, south: 40, east: 10, north: 50})
        ).toEqual({top: 55, bottom: 35, left: -5, right: 15});
        expect(
            clusterBounds({west: -200, south: -80, east: 200, north: 80})
        ).toBeUndefined();
        expect(
            clusterBounds({west: 0, south: 70, east: 10, north: 84})
        ).toMatchObject({top: 85.0511});
    });

    it('tells whether the clustered box still holds the view', () => {
        const box = {top: 55, bottom: 35, left: -5, right: 15};
        expect(coversArea(box, {west: 0, south: 40, east: 10, north: 50})).toBe(
            true
        );
        expect(
            coversArea(box, {west: -6, south: 40, east: 10, north: 50})
        ).toBe(false);
        expect(
            coversArea(undefined, {west: -6, south: 40, east: 10, north: 50})
        ).toBe(true);
        expect(
            coversArea(
                {top: 10, bottom: 0, left: 170, right: -170},
                {west: 175, south: 2, east: 179, north: 5}
            )
        ).toBe(false);
    });

    it('wraps longitudes', () => {
        expect(wrapLongitude(190)).toBe(-170);
        expect(wrapLongitude(-190)).toBe(170);
        expect(wrapLongitude(180)).toBe(180);
        expect(wrapLongitude(45)).toBe(45);
    });

    it('measures distances on the ground', () => {
        const paris = {lat: 48.8566, lng: 2.3522};
        const london = {lat: 51.5074, lng: -0.1278};
        expect(distance(paris, london) / 1000).toBeCloseTo(343.5, 0);
    });

    it('draws a closed ring at the circle radius', () => {
        const circle = {lat: 45, lng: 5, radius: 2000};
        const ring = circleRing(circle, 16);
        expect(ring).toHaveLength(17);
        expect(ring[0]).toEqual(ring[16]);
        for (const [lng, lat] of ring) {
            expect(distance(circle, {lat, lng})).toBeCloseTo(2000, 0);
        }
    });

    it('writes and reads back the zones condition', () => {
        const circles = [
            {lat: 48.8566, lng: 2.3522, radius: 1500.4},
            {lat: -33.86, lng: -70.65, radius: 12000},
        ];
        const query = circlesToAQL('location', circles);
        expect(query).toBe(
            'location WITHIN CIRCLE (48.8566, 2.3522, "1500m") OR location WITHIN CIRCLE (-33.86, -70.65, "12000m")'
        );
        expect(parseCircles(query, 'location')).toEqual([
            {lat: 48.8566, lng: 2.3522, radius: 1500},
            {lat: -33.86, lng: -70.65, radius: 12000},
        ]);
    });

    it('reads radiuses in meters or with a unit', () => {
        expect(parseCircles('loc WITHIN CIRCLE (1, 2, 300)', 'loc')).toEqual([
            {lat: 1, lng: 2, radius: 300},
        ]);
        expect(
            parseCircles('loc WITHIN CIRCLE (1, 2, "2.5km")', 'loc')
        ).toEqual([{lat: 1, lng: 2, radius: 2500}]);
    });

    it('does not read conditions the map cannot draw', () => {
        expect(
            parseCircles('loc WITHIN CIRCLE (1, 2, 3)', 'other')
        ).toBeUndefined();
        expect(
            parseCircles(
                'loc WITHIN CIRCLE (1, 2, 3) AND loc WITHIN CIRCLE (1, 2, 4)',
                'loc'
            )
        ).toBeUndefined();
        expect(
            parseCircles('loc WITHIN RECTANGLE (1, 2, 3, 4)', 'loc')
        ).toBeUndefined();
        expect(parseCircles('not AQL (', 'loc')).toBeUndefined();
    });

    it('reads the area of a single geo condition', () => {
        expect(
            conditionCircle(
                condition('loc WITHIN CIRCLE (48.85, -2.35, "3km")')
            )
        ).toEqual({lat: 48.85, lng: -2.35, radius: 3000});
        expect(
            conditionBounds(condition('loc WITHIN RECTANGLE (50, -1.5, 40, 3)'))
        ).toEqual({top: 50, left: -1.5, bottom: 40, right: 3});
        expect(
            conditionCircle(condition('loc WITHIN RECTANGLE (50, -1.5, 40, 3)'))
        ).toBeUndefined();
        expect(
            conditionBounds(condition('loc WITHIN RECTANGLE ("a", 1, 2, 3)'))
        ).toBeUndefined();
    });

    it('formats coordinates with a decimal point in every language', () => {
        expect(formatLatLng({lat: 48.856614, lng: -2.3522219})).toBe(
            '48.8566, -2.3522'
        );
    });

    it('grabs the outline of a zone or the smallest zone around a point', () => {
        const big = {lat: 45, lng: 5, radius: 10000};
        const small = {lat: 45, lng: 5.05, radius: 1000};
        const east = (c: typeof big, meters: number) =>
            destination(c, meters, Math.PI / 2);

        expect(hitZone([big, small], east(big, 9990), 20)).toEqual({
            index: 0,
            part: 'edge',
        });
        expect(hitZone([big, small], east(big, 2000), 20)).toEqual({
            index: 0,
            part: 'inside',
        });
        expect(hitZone([big, small], small, 20)).toEqual({
            index: 1,
            part: 'inside',
        });
        expect(hitZone([small, big], small, 20)).toEqual({
            index: 0,
            part: 'inside',
        });
        expect(hitZone([big], east(big, 12000), 20)).toBeUndefined();
    });

    it('moves a zone by the drag', () => {
        expect(
            moveCircle(
                {lat: 45, lng: 179, radius: 500},
                {lat: 44, lng: 178},
                {lat: 45.5, lng: 180.5}
            )
        ).toEqual({lat: 46.5, lng: -178.5, radius: 500});
    });

    it('writes a rectangle condition', () => {
        expect(
            boundsToAQL('loc', {
                top: 50,
                left: -1.5,
                bottom: 40.1234567,
                right: 3,
            })
        ).toBe('loc WITHIN RECTANGLE (50, -1.5, 40.123457, 3)');
    });

    it('gives an area to a box around a single position', () => {
        const b = padBounds({top: 45, left: 5, bottom: 45, right: 5});
        expect(b.top).toBeGreaterThan(45);
        expect(b.bottom).toBeLessThan(45);
        expect(
            distance({lat: b.top, lng: 5}, {lat: b.bottom, lng: 5})
        ).toBeCloseTo(10, 0);
    });

    it('formats distances', () => {
        expect(formatDistance(850.4, 'en')).toBe('850 m');
        expect(formatDistance(1530, 'en')).toBe('1.5 km');
        expect(formatDistance(25400, 'fr')).toBe('25 km');
    });
});
