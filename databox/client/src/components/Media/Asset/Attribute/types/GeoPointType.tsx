import {
    AttributeFormatterProps,
    AttributeWidgetProps,
    AvailableFormat,
} from './types';
import TextType from './TextType';
import {Marker, Popup} from 'react-leaflet';
import OpenStreetMap from '../../../../Map/OpenStreetMap';
import React from 'react';

enum Formats {
    Map = 'map',
    Coords = 'coords',
}

type GeoPoint = {
    lat: number;
    lng: number;
};

export default class GeoPointType extends TextType {
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
        const {value, format} = props;

        if (!value) {
            return;
        }

        const {lng, lat} = value;

        switch (format ?? this.getDefaultFormat()) {
            case Formats.Map: {
                const position = {
                    lat,
                    lng,
                };

                return (
                    <OpenStreetMap
                        center={position}
                        zoom={13}
                        scrollWheelZoom={false}
                    >
                        <Marker position={position}>
                            <Popup>{this.formatValueAsString(props)}</Popup>
                        </Marker>
                    </OpenStreetMap>
                );
            }
            default:
            case Formats.Coords:
                return (
                    <>
                        {lng}, {lat}
                    </>
                );
        }
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value ? `${value.lng}, ${value.lat}` : undefined;
    }

    getAvailableFormats(): AvailableFormat[] {
        return [
            {
                name: Formats.Coords,
                title: 'Coords',
            },
            {
                name: Formats.Map,
                title: 'Map',
            },
        ];
    }
}
