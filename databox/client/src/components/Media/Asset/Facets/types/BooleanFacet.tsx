import {
    Checkbox,
    ListItemButton,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import {ListFacetItemProps} from './TextFacetItem.tsx';
import ListFacet from './ListFacet.tsx';
import {stopPropagation} from '../../../../../lib/stdFuncs.ts';
import {useTranslation} from 'react-i18next';
import {FacetGroupProps} from '../facetTypes.ts';

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
                              defaultValue: `Yes ({{n}})`,
                          })
                        : t('facet.boolean.false.label', {
                              n: count,
                              defaultValue: `No ({{n}})`,
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
