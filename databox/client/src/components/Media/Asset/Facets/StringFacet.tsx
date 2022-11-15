import React, {useContext} from 'react';
import {Checkbox, List, ListItemButton, ListItemSecondaryAction, ListItemText} from "@mui/material";
import {extractLabelValueFromKey, FacetRowProps} from "../Facets";
import {SearchContext} from "../../Search/SearchContext";


export default function StringFacet({
                                        facet,
                                        name,
                                    }: FacetRowProps) {
    const {attrFilters, toggleAttrFilter} = useContext(SearchContext);
    const attrFilter = attrFilters.find(_f => _f.a === name && !_f.i);

    return <>
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
                            inputProps={{'aria-labelledby': keyV as string}}
                        />
                    </ListItemSecondaryAction>
                </ListItemButton>
            })}
        </List>
    </>
}
