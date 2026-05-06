import {Checkbox, ListItem} from '@mui/material';
import {ListFacetItemProps} from './TextFacetItem.tsx';
import ListFacet from './ListFacet.tsx';
import {stopPropagation} from '../../../../../lib/stdFuncs.ts';
import {FacetGroupProps} from '../facetTypes.ts';
import {AttributeEntity} from '../../../../../types.ts';
import AttributeEntityListText from '../../Attribute/AttributeEntityListText.tsx';

function EntityFacetItem({
    onClick,
    selected,
    labelValue,
    count,
}: ListFacetItemProps) {
    const {item, value} = labelValue;

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
            <AttributeEntityListText
                data={item as AttributeEntity}
                suffix={` (${count})`}
                inList={true}
            />
        </ListItem>
    );
}

export default function EntitiesFacet(props: FacetGroupProps) {
    return <ListFacet {...props} itemComponent={EntityFacetItem} />;
}
