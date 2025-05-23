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
import {AQLConditionBuilder} from '../../Search/AQL/AQLConditionBuilder.ts';
import {parseAQLQuery} from '../../Search/AQL/AQL.ts';
import {extractField} from './attributeUtils.ts';

type Props = {
    itemComponent: React.FC<ListFacetItemProps>;
} & FacetGroupProps;

export default function ListFacet({facet, name, itemComponent}: Props) {
    const {conditions, upsertCondition, removeCondition} =
        useContext(SearchContext)!;
    const condition = conditions.find(_f => _f.id === name);
    const {type} = facet.meta;
    const {t} = useTranslation();
    const fieldName = extractField(name);

    const queryBuilder = AQLConditionBuilder.fromQuery(
        fieldName,
        condition ? parseAQLQuery(condition.query) : undefined
    );

    const missingOnClick = () => {
        upsertCondition({
            id: name,
            query: `${fieldName} IS MISSING`,
        });
    };
    const missingSelected = Boolean(
        condition && !condition.disabled && queryBuilder.includeMissing
    );

    return (
        <>
            <List component="div" disablePadding>
                {facet.buckets.map(b => {
                    const labelValue = extractLabelValueFromKey(b.key, type);
                    const {value: keyV} = labelValue;

                    const selected = Boolean(
                        condition &&
                            !condition.disabled &&
                            queryBuilder.hasValue(keyV)
                    );
                    const onClick = () => {
                        const query = queryBuilder.toggleValue(keyV).toString();
                        if (query === '') {
                            removeCondition({
                                id: name,
                                query,
                            });

                            return;
                        }

                        upsertCondition({
                            id: name,
                            query,
                        });
                    };

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
