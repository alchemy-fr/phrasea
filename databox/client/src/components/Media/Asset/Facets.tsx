import {useContext, useState} from "react";
import {ResultContext} from "../Search/ResultContext";
import {
    Checkbox,
    Collapse,
    List,
    ListItem,
    ListItemButton,
    ListItemSecondaryAction,
    ListItemText,
    ListSubheader
} from "@mui/material";
import {ExpandLess, ExpandMore} from "@mui/icons-material";
import {SearchContext} from "../Search/SearchContext";

export type BucketKeyValue = string | {
    value: string;
    label: string;
}

type Bucket = {
    key: BucketKeyValue;
    doc_count: number;
}

type Facet = {
    meta: {
        title: string;
    };
    buckets: Bucket[];
    doc_count_error_upper_bound: number;
    sum_other_doc_count: number;
}

export type TFacets = Record<string, Facet>;

export function extractLabelValueFromKey(key: BucketKeyValue): {
    label: string;
    value: string;
} {
    if (typeof key === 'string') {
        return {
            label: key,
            value: key,
        };
    }

    return key;
}

function FacetRow({
                      facet,
                      name,
                  }: {
    facet: Facet;
    name: string;
}) {
    const {attrFilters, toggleAttrFilter} = useContext(SearchContext);
    const [open, setOpen] = useState(true);

    const attrFilter = attrFilters.find(_f => _f.a === name && !_f.i);

    return <>
        <ListItem button onClick={() => setOpen(o => !o)}>
            <ListItemText primary={facet.meta.title}/>
            {open ? <ExpandLess/> : <ExpandMore/>}
        </ListItem>
        <Collapse in={open} timeout="auto" unmountOnExit>
            <List component="div" disablePadding>
                {facet.buckets.map(b => {
                    const {value: keyV, label} = extractLabelValueFromKey(b.key);
                    const selected = Boolean(attrFilter && attrFilter.v.find(v => extractLabelValueFromKey(v).value === keyV));

                    const onClick = () => toggleAttrFilter(name, b.key, facet.meta.title);

                    return <ListItemButton
                        key={keyV}
                        onClick={onClick}
                    >
                        <ListItemText secondary={`${label} (${b.doc_count})`}/>
                        <ListItemSecondaryAction>
                            <Checkbox
                                edge="end"
                                onChange={onClick}
                                checked={selected || false}
                                inputProps={{ 'aria-labelledby': keyV }}
                            />
                        </ListItemSecondaryAction>
                    </ListItemButton>
                })}
            </List>
        </Collapse>
    </>
}

export default function Facets() {
    const search = useContext(ResultContext);
    const {facets} = search;

    if (!facets) {
        return null;
    }

    return <List
        component="nav"
        aria-labelledby="nested-list-subheader"
        subheader={
            <ListSubheader component="div" id="nested-list-subheader">
                Facets
            </ListSubheader>
        }
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
