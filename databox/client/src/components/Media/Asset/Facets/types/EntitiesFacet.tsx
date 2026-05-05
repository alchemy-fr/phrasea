import {Checkbox, ListItem, ListItemText} from '@mui/material';
import {ListFacetItemProps} from './TextFacetItem.tsx';
import ListFacet from './ListFacet.tsx';
import {getBestLocaleOfTranslations} from '@alchemy/i18n/src/Locale/localeHelper.ts';
import {stopPropagation} from '../../../../../lib/stdFuncs.ts';
import {FacetGroupProps} from '../facetTypes.ts';

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
        <ListItem
            onClick={onClick}
            secondaryAction={
                <Checkbox
                    edge="end"
                    onChange={onClick}
                    onClick={stopPropagation}
                    checked={selected}
                    inputProps={{'aria-labelledby': value.toString()}}
                />
            }
        >
            <ListItemText secondary={`${finalLabel} (${count})`} />
        </ListItem>
    );
}

export default function EntitiesFacet(props: FacetGroupProps) {
    return <ListFacet {...props} itemComponent={EntityFacetItem} />;
}
