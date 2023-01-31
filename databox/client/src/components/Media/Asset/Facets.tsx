import React, {useContext, useState} from "react";
import {ResultContext} from "../Search/ResultContext";
import {Collapse, List, ListItem, ListItemButton, ListItemText} from "@mui/material";
import {ExpandLess, ExpandMore} from "@mui/icons-material";
import TextFacet from "./Facets/TextFacet";
import DateHistogramFacet from "./Facets/DateHistogramFacet";
import moment from "moment";
import GeoDistanceFacet from "./Facets/GeoDistanceFacet";
import {AttributeType} from "../../../api/attributes";
import {getAttributeType} from "./Attribute/types";

export type BucketValue = string | number | boolean;

export type LabelledBucketValue = {
    label: string;
    value: BucketValue;
}

export type ResolvedBucketValue = BucketValue | LabelledBucketValue;

export type NormalizedBucketKeyValue = BucketValue | {
    l: string;
    v: BucketValue;
}

export type Bucket = {
    key: BucketValue;
    doc_count: number;
}

export enum FacetType {
    String = 'string',
    Boolean = 'string',
    DateRange = 'date_range',
    GeoDistance = 'geo_distance',
}

export type Facet = {
    meta: {
        title: string;
        widget?: FacetType;
        type?: AttributeType;
        sortable: boolean;
    };
    buckets: Bucket[];
    doc_count_error_upper_bound: number;
    sum_other_doc_count: number;
    interval?: string;
}

export type TFacets = Record<string, Facet>;

export function extractLabelValueFromKey(key: ResolvedBucketValue, type: AttributeType | undefined): LabelledBucketValue {
    if (typeof key === 'object' && key.hasOwnProperty('value')) {
        return key as LabelledBucketValue;
    }

    type = type ?? AttributeType.Text;
    const t = getAttributeType(type);

    if ([
        AttributeType.DateTime,
        AttributeType.Date,
    ].includes(type)) {
        return {
            label: t.formatValueAsString({
                value: key,
            })!,
            value: key as BucketValue,
        };
    } else if (type === AttributeType.Boolean) {
        return {
            label: t.formatValueAsString({
                value: !!key,
            })!,
            value: !!key,
        };
    }

    return {
        label: t.formatValueAsString({
            value: key as string,
        })!,
        value: key as BucketValue,
    };
}

export type FacetRowProps = {
    facet: Facet;
    name: string;
}

const facetWidgets: Record<FacetType, React.FC<FacetRowProps>> = {
    [FacetType.String]: TextFacet,
    [FacetType.DateRange]: DateHistogramFacet,
    [FacetType.GeoDistance]: GeoDistanceFacet,
}

function FacetRow({
                      facet,
                      name,
                  }: FacetRowProps) {
    const [open, setOpen] = useState(true);

    const widget = facet.meta.widget ?? FacetType.String;

    return <>
        <ListItem
            sx={{
                backgroundColor: 'primary.main',
                color: 'primary.contrastText',
            }}
            disablePadding
        >
            <ListItemButton
                onClick={() => setOpen(o => !o)}
            >
                <ListItemText primary={facet.meta.title}/>
                {open ? <ExpandLess/> : <ExpandMore/>}
            </ListItemButton>
        </ListItem>
        <Collapse in={open} timeout="auto" unmountOnExit>
            {React.createElement(facetWidgets[widget] ?? facetWidgets[FacetType.String], {
                facet,
                name,
            })}
        </Collapse>
    </>
}

export default function Facets() {
    const {facets} = useContext(ResultContext);

    if (!facets) {
        return null;
    }

    return <List
        disablePadding
        component="nav"
        aria-labelledby="nested-list-subheader"
        sx={(theme) => ({
            root: {
                width: '100%',
                maxWidth: 360,
                backgroundColor: theme.palette.background.paper,
            },
            nested: {
                paddingLeft: theme.spacing(4),
            }
        })}
    >
        {Object.keys(facets).filter(k => facets[k].buckets.length > 0).map((k) => <FacetRow
            key={k}
            name={k}
            facet={facets[k]}
        />)}
    </List>
}
