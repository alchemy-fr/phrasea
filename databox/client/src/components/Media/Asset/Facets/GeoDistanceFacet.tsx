import React, {useContext} from 'react';
import {Bucket, Facet, FacetGroupProps} from "../Facets";
import {useTheme} from "@mui/material";
import {SearchContext} from "../../Search/SearchContext";
import {Circle, Marker, Popup} from "react-leaflet";
import OpenStreetMap from "../../../Map/OpenStreetMap";


export default function GeoDistanceFacet({
    facet,
    name,
}: FacetGroupProps) {
    const {attrFilters, setAttrFilter, removeAttrFilter} = useContext(SearchContext);
    const attrFilterIndex = attrFilters.findIndex(_f => _f.a === name);
    const attrFilter = attrFilterIndex >= 0 ? attrFilters[attrFilterIndex] : undefined;
    const theme = useTheme();

    const colors = [
        theme.palette.primary.main,
        theme.palette.error.main
    ];

    const meta = facet.meta as {
        position: [number, number];
    } & Facet['meta'];

    return <OpenStreetMap
        center={meta.position}
        zoom={13}
        scrollWheelZoom={false}
        height={300}
    >
        {(facet.buckets as ({ to?: number, from?: number } & Bucket)[]).filter(b => Boolean(b.to) && b.doc_count > 0)
            .map((b, i) => {

                return <React.Fragment
                    key={b.key.toString()}>
                    <Marker
                        position={meta.position}
                    >
                        <Popup>
                        <span>
                            {b.doc_count}
                        </span>
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
            })}
        <Marker position={meta.position}>
        </Marker>
    </OpenStreetMap>
}
