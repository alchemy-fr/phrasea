import React from 'react';
import {AttributeFormatterProps, AttributeWidgetProps, AvailableFormat} from "./types";
import TextType from "./TextType";
import {MapContainer, Marker, Popup, TileLayer} from "react-leaflet";
import {Box} from "@mui/material";

enum Formats {
    Map = 'map',
    Coords = 'coords',
}

type GeoPoint = {
    lat: number;
    lng: number;
}

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
                 }: AttributeWidgetProps): React.ReactNode {
        return super.renderWidget({
            value: this.denormalizeValue(value),
            ...rest
        });
    }

    formatValue(props: AttributeFormatterProps): React.ReactNode {
        const {value, format} = props;

        if (!value) {
            return;
        }

        const {lng, lat} = value;

        switch (format ?? this.getDefaultFormat()) {
            case Formats.Map:
                const position = {
                    lat,
                    lng,
                };

                return <Box sx={{
                    overflow: 'hidden',
                    position: 'relative',
                    zIndex: 0,
                    '.leaflet-container': {
                        width: 500,
                        height: 300,
                    }
                }}>
                    <MapContainer center={position} zoom={13} scrollWheelZoom={false}>
                        <TileLayer
                            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                        />
                        <Marker position={position}>
                            <Popup>
                                {this.formatValueAsString(props)}
                            </Popup>
                        </Marker>
                    </MapContainer>
                </Box>
            default:
            case Formats.Coords:
                return <>{lng}, {lat}</>
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
