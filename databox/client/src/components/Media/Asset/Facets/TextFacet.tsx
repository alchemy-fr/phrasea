import React from 'react';
import {FacetGroupProps} from "../Facets";
import ListFacet from "./ListFacet";
import TextFacetItem from "./TextFacetItem";

export default function TextFacet(props: FacetGroupProps) {
    return <ListFacet
        {...props}
        itemComponent={TextFacetItem}
    />
}
