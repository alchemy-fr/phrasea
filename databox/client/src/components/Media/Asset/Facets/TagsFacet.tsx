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
import {useTranslation} from 'react-i18next';

function TagFacetItem({
    onClick,
    selected,
    labelValue,
    count,
}: ListFacetItemProps) {
    const {item, label, value} = labelValue;

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
            <ListItemText secondary={`${label} (${count})`} />
            <ListItemSecondaryAction>
                <Checkbox
                    edge="end"
                    onChange={onClick}
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
