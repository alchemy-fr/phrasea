import React, {useContext} from 'react';
import {
    Checkbox,
    List,
    ListItemButton,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import {extractLabelValueFromKey, FacetGroupProps} from '../Facets';
import {SearchContext} from '../../Search/SearchContext';
import {ListFacetItemProps} from './TextFacetItem';

type Props = {
    itemComponent: React.FC<ListFacetItemProps>;
} & FacetGroupProps;

export default function ListFacet({facet, name, itemComponent}: Props) {
    const {attrFilters, toggleAttrFilter} = useContext(SearchContext);
    const attrFilter = attrFilters.find(_f => _f.a === name && !_f.i);
    const {type} = facet.meta;

    const missingOnClick = () => {
        toggleAttrFilter(name, 'missing', '', facet.meta.title);
    };
    const missingSelected = Boolean(
        attrFilter &&
            attrFilter.v.some(
                v => extractLabelValueFromKey(v, type).value === ''
            )
    );

    return (
        <>
            <List component="div" disablePadding>
                {facet.buckets.map(b => {
                    const labelValue = extractLabelValueFromKey(b.key, type);
                    const {value: keyV} = labelValue;

                    const selected = Boolean(
                        attrFilter &&
                            attrFilter.v.some(
                                v =>
                                    extractLabelValueFromKey(v, type).value ===
                                    keyV
                            )
                    );

                    const onClick = () =>
                        toggleAttrFilter(
                            name,
                            facet.meta.type,
                            b.key,
                            facet.meta.title
                        );

                    return React.createElement(itemComponent, {
                        key: keyV.toString(),
                        onClick,
                        selected,
                        labelValue,
                        count: b.doc_count,
                    });
                })}
                {facet.missing_count ? (
                    <ListItemButton onClick={missingOnClick}>
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
                    </ListItemButton>
                ) : (
                    ''
                )}
            </List>
        </>
    );
}
