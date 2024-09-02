import {PropsWithChildren} from 'react';
import {MapContainer, TileLayer} from 'react-leaflet';
import {Box} from '@mui/material';
import {MapOptions} from 'leaflet';

type Props = PropsWithChildren<{
    width?: number | string;
    height?: number | string;
}> &
    MapOptions;

export default function OpenStreetMap({
    width,
    height,
    children,
    ...mapProps
}: Props) {
    return (
        <Box
            sx={{
                'overflow': 'hidden',
                'position': 'relative',
                'zIndex': 0,
                '.leaflet-container': {
                    width,
                    height,
                },
            }}
        >
            <MapContainer {...mapProps}>
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {children}
            </MapContainer>
        </Box>
    );
}
