import {
    Box,
    Checkbox,
    ListItemButton,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import {FacetGroupProps} from '../Facets';
import {ListFacetItemProps} from './TextFacetItem';
import ListFacet from './ListFacet';
import {getBestLocaleOfTranslations} from '@alchemy/i18n/src/Locale/localeHelper';
import {stopPropagation} from "../../../../lib/stdFuncs.ts";

function EntityFacetItem({
    onClick,
    selected,
    labelValue,
    count,
}: ListFacetItemProps) {
    const {item, label, value} = labelValue;

    const l = getBestLocaleOfTranslations(item?.translations ?? {});
    const finalLabel = l ? item!.translations![l]! : label;

    return (
        <ListItemButton onClick={onClick}>
            <ListItemText secondary={`${finalLabel} (${count})`} />
            <ListItemSecondaryAction>
                <Checkbox
                    edge="end"
                    onChange={onClick}
                    onClick={stopPropagation}
                    checked={selected}
                    inputProps={{'aria-labelledby': value.toString()}}
                />
            </ListItemSecondaryAction>
        </ListItemButton>
    );
}

export default function EntitiesFacet(props: FacetGroupProps) {
    return <ListFacet {...props} itemComponent={EntityFacetItem} />;
}
