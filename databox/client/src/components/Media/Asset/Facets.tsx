import {useContext, useState} from "react";
import {ResultContext} from "../Search/ResultContext";
import {Collapse, List, ListItem, ListItemButton, ListItemText} from "@mui/material";
import {ExpandLess, ExpandMore} from "@mui/icons-material";
import StringFacet from "./Facets/StringFacet";
import DateHistogramFacet from "./Facets/DateHistogramFacet";
import moment from "moment";

export type BucketKeyValue = string | number | {
    value: string;
    label: string;
}

export type NormalizedBucketKeyValue = string | number | {
    v: string;
    l: string;
}

type Bucket = {
    key: BucketKeyValue;
    doc_count: number;
}

export enum FacetType {
    String = 'string',
    DateRange = 'date_range',
}

export type Facet = {
    meta: {
        title: string;
        type?: FacetType;
    };
    buckets: Bucket[];
    doc_count_error_upper_bound: number;
    sum_other_doc_count: number;
}

export type TFacets = Record<string, Facet>;

export function extractLabelValueFromKey(key: BucketKeyValue): {
    label: string;
    value: string | number;
} {
    if (typeof key === 'string') {
        return {
            label: key,
            value: key,
        };
    } else if (typeof key === 'number') {
        return {
            label: moment(key * 1000).format('ll'),
            value: key,
        };
    }

    return key;
}

export type FacetRowProps = {
    facet: Facet;
    name: string;
}

function FacetRow({
                      facet,
                      name,
                  }: FacetRowProps) {
    const [open, setOpen] = useState(true);

    const type = facet.meta.type ?? FacetType.String;

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
            {type === FacetType.String && <StringFacet
                facet={facet}
                name={name}
            />}
            {type === FacetType.DateRange && <DateHistogramFacet
                facet={facet}
                name={name}
            />}

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
