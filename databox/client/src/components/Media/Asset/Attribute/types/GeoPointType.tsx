import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
    AttributeWidgetProps,
    AvailableFormat,
} from './types';
import TextType from './TextType';
import React from 'react';

// Lazy so that leaflet (which touches `document` at import time)
// stays out of the server-side rendering module graph
const GeoPointMap = React.lazy(() => import('./GeoPointMap'));

enum Formats {
    Map = 'map',
    Coords = 'coords',
    Json = 'json',
}

type GeoPoint = {
    lat: number;
    lng: number;
};

export default class GeoPointType extends TextType {
    public isRich = true;

    denormalizeValue(value: GeoPoint | string): string | undefined {
        if (!value) {
            return;
        }
        if (typeof value === 'string') {
            return value;
        }

        return `${value.lat}, ${value.lng}`;
    }

    renderWidget({
        value,
        ...rest
    }: AttributeWidgetProps<string>): React.ReactNode {
        return super.renderWidget({
            value: this.denormalizeValue(value),
            ...rest,
        });
    }

    formatValue(props: AttributeFormatterProps): React.ReactNode {
        const {value, format, ...options} = props;
        if (!value) {
            return;
        }

        const {lng, lat} = value;

        switch (format ?? this.getDefaultFormat(options)) {
            case Formats.Map: {
                const position = {
                    lat,
                    lng,
                };

                return (
                    <React.Suspense fallback={null}>
                        <GeoPointMap
                            position={position}
                            popup={this.formatValueAsString(props)}
                        />
                    </React.Suspense>
                );
            }
            default:
            case Formats.Coords:
                return (
                    <>
                        Longitude: {lng}, Latitude: {lat}
                    </>
                );
            case Formats.Json:
                return (
                    <code>
                        {JSON.stringify({
                            lat,
                            lng,
                        })}
                    </code>
                );
        }
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value ? `${value.lng}, ${value.lat}` : undefined;
    }

    getAvailableFormats(options: AttributeFormatterOptions): AvailableFormat[] {
        return [
            {
                name: Formats.Coords,
                title: 'Coords',
            },
            {
                name: Formats.Map,
                title: 'Map',
            },
            {
                name: Formats.Json,
                title: 'JSON',
            },
        ].map(f => ({
            ...f,
            example: this.formatValue({
                ...options,
                value: {lng: 2.2945, lat: 48.8584},
                format: f.name,
            }),
        }));
    }
}
