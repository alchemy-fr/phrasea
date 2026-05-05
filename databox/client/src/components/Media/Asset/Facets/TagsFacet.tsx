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
import {Tag} from '../../../../types.ts';
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
