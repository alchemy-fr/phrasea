import {PropsWithChildren} from 'react';
import {MapContainer, TileLayer} from 'react-leaflet';
import {Box} from '@mui/material';
import {MapOptions} from 'leaflet';
import {useTranslation} from 'react-i18next';

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
    const {t} = useTranslation();
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
                    attribution={t(
                        'open_street_map.copy_a_href_https_www_openstreetmap_org_copyright_open_street_map_a_contributors',
                        `&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors`
                    )}
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {children}
            </MapContainer>
        </Box>
    );
}
