import {
    Checkbox,
    ListItemButton,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import {FacetGroupProps} from '../Facets';
import {ListFacetItemProps} from './TextFacetItem';
import ListFacet from './ListFacet';
import {stopPropagation} from '../../../../lib/stdFuncs.ts';
import {useTranslation} from 'react-i18next';

function BooleanItem({
    onClick,
    selected,
    labelValue,
    count,
}: ListFacetItemProps) {
    const {t} = useTranslation();

    return (
        <ListItemButton onClick={onClick}>
            <ListItemText
                primary={
                    labelValue.value
                        ? t('facet.boolean.true.label', {
                              n: count,
                              defaultValue: `True ({{n}})`,
                          })
                        : t('facet.boolean.false.label', {
                              n: count,
                              defaultValue: `False ({{n}})`,
                          })
                }
            />
            <ListItemSecondaryAction>
                <Checkbox
                    edge="end"
                    onChange={onClick}
                    onClick={stopPropagation}
                    checked={selected}
                    inputProps={{
                        'aria-labelledby': labelValue.value.toString(),
                    }}
                />
            </ListItemSecondaryAction>
        </ListItemButton>
    );
}

export default function BooleanFacet(props: FacetGroupProps) {
    return <ListFacet {...props} itemComponent={BooleanItem} />;
}
