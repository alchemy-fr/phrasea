import {useContext, useState} from "react";
import {SearchContext} from "../Search/SearchContext";
import {Checkbox, Collapse, List, ListItem, ListItemSecondaryAction, ListItemText, ListSubheader} from "@mui/material";
import {createStyles, makeStyles, Theme} from "@material-ui/core/styles";
import {ExpandLess, ExpandMore} from "@material-ui/icons";

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


const useStyles = makeStyles((theme: Theme) =>
    createStyles({
        root: {
            width: '100%',
            maxWidth: 360,
            backgroundColor: theme.palette.background.paper,
        },
        nested: {
            paddingLeft: theme.spacing(4),
        },
    }),
);

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

                    return <ListItem
                        button
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
                    </ListItem>
                })}
            </List>
        </Collapse>
    </>
}

export default function Facets() {
    const search = useContext(SearchContext);
    const classes = useStyles();
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
        className={classes.root}
    >
        {Object.keys(facets).filter(k => facets[k].buckets.length > 0).map((k) => <FacetRow
            key={k}
            name={k}
            facet={facets[k]}
        />)}
    </List>
}
