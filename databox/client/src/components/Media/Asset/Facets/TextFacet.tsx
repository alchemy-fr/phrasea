import React, {useContext} from 'react';
import {Checkbox, List, ListItemButton, ListItemSecondaryAction, ListItemText} from "@mui/material";
import {extractLabelValueFromKey, FacetGroupProps} from "../Facets";
import {SearchContext} from "../../Search/SearchContext";

export default function TextFacet({
    facet,
    name,
}: FacetGroupProps) {
    const {attrFilters, toggleAttrFilter} = useContext(SearchContext);
    const attrFilter = attrFilters.find(_f => _f.a === name && !_f.i);
    const {type} = facet.meta;

    const missingOnClick = () => {
        toggleAttrFilter(name, 'missing', '', facet.meta.title);
    };
    const missingSelected = Boolean(attrFilter && attrFilter.v.some(v => extractLabelValueFromKey(v, type).value === ''));

    return <>
        <List component="div" disablePadding>
            {facet.buckets.map(b => {
                const {value: keyV, label} = extractLabelValueFromKey(b.key, type);

                const selected = Boolean(attrFilter && attrFilter.v.some(v => extractLabelValueFromKey(v, type).value === keyV));

                const onClick = () => toggleAttrFilter(name, facet.meta.type, b.key, facet.meta.title);

                return <ListItemButton
                    key={keyV.toString()}
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
            {facet.missing_count ? <ListItemButton
                onClick={missingOnClick}
            >
                <ListItemText
                    secondary={`Missing (${facet.missing_count})`}
                    secondaryTypographyProps={{
                        color: 'info',
                    }}
                />
                <ListItemSecondaryAction>
                    <Checkbox
                        edge="end"
                        onChange={missingOnClick}
                        checked={missingSelected}
                        inputProps={{'aria-labelledby': 'Missing'}}
                    />
                </ListItemSecondaryAction>
            </ListItemButton> : ''}
        </List>
    </>
}
