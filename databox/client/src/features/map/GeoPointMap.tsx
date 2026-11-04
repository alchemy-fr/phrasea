'use client';

import 'mapbox-gl/dist/mapbox-gl.css';
import './map.css';
import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useTheme} from 'next-themes';
import {Map as MapboxMap, Marker, NavigationControl, Popup} from 'mapbox-gl';
import {resolveMapColors} from './mapColors';
import {styleUrls} from './mapStyles';
import {formatLatLng, LngLat} from './geo';

/**
 * A small map centered on a geo point attribute value, with a marker on it.
 */
export default function GeoPointMap({
    token,
    point,
}: {
    token: string;
    point: LngLat;
}) {
    const {i18n} = useTranslation();
    const {resolvedTheme} = useTheme();
    const containerRef = useRef<HTMLDivElement>(null);
    const [map, setMap] = useState<MapboxMap | null>(null);
    const styleUrl =
        resolvedTheme === 'dark' ? styleUrls.dark : styleUrls.light;
    const {lng, lat} = point;

    useEffect(() => {
        const center: [number, number] = [lng, lat];
        const m = new MapboxMap({
            container: containerRef.current!,
            accessToken: token,
            style: styleUrl,
            center,
            zoom: 13,
            language: i18n.language,
            // The map sits among the attributes: the wheel scrolls them
            scrollZoom: false,
        });
        m.addControl(new NavigationControl({showCompass: false}), 'top-right');
        new Marker({color: resolveMapColors().primary})
            .setLngLat(center)
            .setPopup(new Popup({offset: 25}).setText(formatLatLng({lng, lat})))
            .addTo(m);

        const resizeObserver = new ResizeObserver(() => m.resize());
        resizeObserver.observe(containerRef.current!);
        setMap(m);

        return () => {
            resizeObserver.disconnect();
            m.remove();
            setMap(null);
        };
        // The style changes on the map
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [token, lng, lat]);

    const appliedStyle = useRef(styleUrl);
    useEffect(() => {
        if (map && appliedStyle.current !== styleUrl) {
            appliedStyle.current = styleUrl;
            map.setStyle(styleUrl, {
                // Required by the typings, the map defaults when undefined
                localFontFamily: undefined,
                localIdeographFontFamily: undefined,
            });
        }
    }, [map, styleUrl]);

    return (
        <div
            ref={containerRef}
            data-testid="geo-point-map"
            className="dbx-map h-48 w-full max-w-sm overflow-hidden rounded-md border"
            // Panning the map must not click or drag what holds it (asset card)
            onPointerDown={e => e.stopPropagation()}
            onClick={e => e.stopPropagation()}
            onDoubleClick={e => e.stopPropagation()}
        />
    );
}
