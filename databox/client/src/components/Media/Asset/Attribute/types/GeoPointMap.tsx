import {ReactNode} from 'react';
import {Marker, Popup} from 'react-leaflet';
import OpenStreetMap from '../../../../Map/OpenStreetMap';

type Props = {
    position: {
        lat: number;
        lng: number;
    };
    popup: ReactNode;
};

export default function GeoPointMap({position, popup}: Props) {
    return (
        <OpenStreetMap
            width={300}
            height={200}
            center={position}
            zoom={13}
            scrollWheelZoom={false}
        >
            <Marker position={position}>
                <Popup>{popup}</Popup>
            </Marker>
        </OpenStreetMap>
    );
}
