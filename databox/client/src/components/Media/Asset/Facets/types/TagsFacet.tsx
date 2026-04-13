import {
    Box,
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

function TagFacetItem({
    onClick,
    selected,
    labelValue,
    count,
}: ListFacetItemProps) {
    const {item, value} = labelValue;

    return (
        <ListItemButton onClick={onClick}>
            <Box
                sx={theme => ({
                    width: 30,
                    height: 22,
                    backgroundColor: item!.color,
                    border: `0.5px solid ${theme.palette.common.black}`,
                    mr: 1,
                    borderRadius: theme.shape.borderRadius,
                })}
            />
            <ListItemText
                secondary={`${(item as Tag).nameTranslated} (${count})`}
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
