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
import {useTranslation} from 'react-i18next';

type Props = {
    itemComponent: React.FC<ListFacetItemProps>;
} & FacetGroupProps;

export default function ListFacet({facet, name, itemComponent}: Props) {
    const {conditions, toggleCondition} = useContext(SearchContext)!;
    const condition = conditions.find(_f => _f.id === name);
    const {type} = facet.meta;
    const {t} = useTranslation();

    const missingOnClick = () => {
        toggleCondition({
            id: name,
            query: `${name} IS MISSING`,
        });
    };
    const missingSelected = Boolean(
        condition && !condition.disabled &&
            condition.query.endsWith(' IS MISSING')
    );

    return (
        <>
            <List component="div" disablePadding>
                {facet.buckets.map(b => {
                    const labelValue = extractLabelValueFromKey(b.key, type);
                    const {value: keyV} = labelValue;

                    const selected = Boolean(
                        condition && !condition.disabled &&
                            condition.query.includes(keyV.toString())
                    );

                    const onClick = () =>
                        toggleCondition({
                            id: name,
                            query: `${name} = "${keyV}"`,
                        });

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
                            secondary={t('facets.missing_with_total', {
                                defaultValue: `Missing ({{total}})`,
                                total: facet.missing_count,
                            })}
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
