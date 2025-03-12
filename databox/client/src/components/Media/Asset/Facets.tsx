import React, {useContext, useState} from 'react';
import {ResultContext} from '../Search/ResultContext';
import {
    Collapse,
    List,
    ListItem,
    ListItemButton,
    ListItemText,
} from '@mui/material';
import {ExpandLess, ExpandMore} from '@mui/icons-material';
import TextFacet from './Facets/TextFacet';
import DateHistogramFacet from './Facets/DateHistogramFacet';
import GeoDistanceFacet from './Facets/GeoDistanceFacet';
import {AttributeType} from '../../../api/attributes';
import {getAttributeType} from './Attribute/types';
import {FilterType} from '../Search/Filter';
import {AttributeFormat} from './Attribute/types/types';
import TagsFacet from './Facets/TagsFacet';
import EntitiesFacet from "./Facets/EntitiesFacet.tsx";
import {BuiltInFilter} from "../Search/search.ts";

export type BucketValue = string | number | boolean;

export type LabelledBucketValue = {
    label: string;
    value: BucketValue;
    item?: Record<string, any>;
};

export type ResolvedBucketValue = BucketValue | LabelledBucketValue;

export type Bucket = {
    key: BucketValue;
    doc_count: number;
};

export enum FacetType {
    Text = 'text',
    Boolean = 'boolean',
    DateRange = 'date_range',
    GeoDistance = 'geo_distance',
    Entity = 'entity',
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
    missing_count?: number;
    interval?: string;
};

export type TFacets = Record<string, Facet>;

export function extractLabelValueFromKey(
    key: ResolvedBucketValue,
    type: FilterType | undefined,
    format?: AttributeFormat
): LabelledBucketValue {
    // eslint-disable-next-line no-prototype-builtins
    if (key && typeof key === 'object' && key.hasOwnProperty('value')) {
        return key as LabelledBucketValue;
    }

    if ('missing' === type) {
        return {
            label: `Missing`,
            value: '__missing__',
        };
    }

    type = type ?? AttributeType.Text;
    const at = getAttributeType(type);

    if ([AttributeType.DateTime, AttributeType.Date].includes(type)) {
        return {
            label: at.formatValueAsString({
                value: key,
                format,
            })!,
            value: key as BucketValue,
        };
    } else if (type === AttributeType.Boolean) {
        return {
            label: at.formatValueAsString({
                value: !!key,
                format,
            })!,
            value: !!key,
        };
    }

    return {
        label: at.formatValueAsString({
            value: key as string,
            format,
        })!,
        value: key as BucketValue,
    };
}

export type FacetGroupProps = {
    facet: Facet;
    name: string;
};

const facetWidgets: Record<FacetType, React.FC<FacetGroupProps>> = {
    [FacetType.Text]: TextFacet,
    [FacetType.Boolean]: TextFacet,
    [FacetType.DateRange]: DateHistogramFacet,
    [FacetType.GeoDistance]: GeoDistanceFacet,
    [FacetType.Entity]: EntitiesFacet,
};

const facetWidgetsByKey: Record<string, React.FC<FacetGroupProps>> = {
    [BuiltInFilter.Tag]: TagsFacet,
};

function FacetGroup({facet, name}: FacetGroupProps) {
    const [open, setOpen] = useState(true);

    const widget =
        facetWidgetsByKey[name] ??
        facetWidgets[facet.meta.widget ?? FacetType.Text] ??
        facetWidgets[FacetType.Text];

    return (
        <>
            <ListItem
                sx={{
                    backgroundColor: 'primary.main',
                    color: 'primary.contrastText',
                }}
                disablePadding
            >
                <ListItemButton onClick={() => setOpen(o => !o)}>
                    <ListItemText primary={facet.meta.title} />
                    {open ? <ExpandLess /> : <ExpandMore />}
                </ListItemButton>
            </ListItem>
            <Collapse in={open} timeout="auto" unmountOnExit>
                {React.createElement(widget, {
                    facet,
                    name,
                })}
            </Collapse>
        </>
    );
}

const Facets = React.memo(function ({facets}: {facets: TFacets}) {
    return (
        <List
            disablePadding
            component="nav"
            aria-labelledby="nested-list-subheader"
            sx={theme => ({
                root: {
                    width: '100%',
                    maxWidth: 360,
                    backgroundColor: theme.palette.background.paper,
                },
                nested: {
                    paddingLeft: theme.spacing(4),
                },
            })}
        >
            {Object.keys(facets)
                .filter(k => facets[k].buckets.length > 0)
                .map(k => (
                    <FacetGroup key={k} name={k} facet={facets[k]} />
                ))}
        </List>
    );
});

export default function FacetsProxy() {
    const {facets} = useContext(ResultContext);

    if (!facets) {
        return null;
    }

    return <Facets facets={facets} />;
}
