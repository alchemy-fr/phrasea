import {Bucket, Facet, FacetGroupProps} from '../Facets';
import {useTheme} from '@mui/material';
import {Circle, Marker, Popup} from 'react-leaflet';
import OpenStreetMap from '../../../Map/OpenStreetMap';

export default function GeoDistanceFacet({facet}: FacetGroupProps) {
    const theme = useTheme();

    const colors = [theme.palette.primary.main, theme.palette.error.main];

    const meta = facet.meta as {
        position: [number, number];
    } & Facet['meta'];

    return (
        <OpenStreetMap
            center={meta.position}
            zoom={13}
            scrollWheelZoom={false}
            height={300}
        >
            {(facet.buckets as ({to?: number; from?: number} & Bucket)[])
                .filter(b => Boolean(b.to) && b.doc_count > 0)
                .map((b, i) => {
                    return (
                        <React.Fragment key={b.key.toString()}>
                            <Marker position={meta.position}>
                                <Popup>
                                    <span>{b.doc_count}</span>
                                </Popup>
                            </Marker>
                            <Circle
                                center={meta.position}
                                radius={b.to}
                                key={b.key.toString()}
                                fillColor={colors[i] ?? colors[0]}
                                color={colors[i] ?? colors[0]}
                            />
                        </React.Fragment>
                    );
                })}
            <Marker position={meta.position}></Marker>
        </OpenStreetMap>
    );
}
