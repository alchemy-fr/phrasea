import {
    Checkbox,
    ListItemButton,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import {ListFacetItemProps} from './TextFacetItem.tsx';
import ListFacet from './ListFacet.tsx';
import {stopPropagation} from '../../../../../lib/stdFuncs.ts';
import {Tag} from '../../../../../types.ts';
import {FacetGroupProps} from '../facetTypes.ts';
import TagColor from './TagColor.tsx';

function TagFacetItem({
    onClick,
    selected,
    labelValue,
    count,
}: ListFacetItemProps) {
    const {item, value} = labelValue;

    return (
        <ListItemButton onClick={onClick}>
            <TagColor color={(item as Tag).color} />
            <ListItemText
                primary={`${(item as Tag).nameTranslated} (${count})`}
            />
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

export default function TagsFacet(props: FacetGroupProps) {
    return <ListFacet {...props} itemComponent={TagFacetItem} />;
}
