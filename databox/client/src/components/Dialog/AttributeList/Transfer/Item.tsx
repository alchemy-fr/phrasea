import {AttributeDefinition, AttributeListItem, AttributeListItemType} from "../../../../types.ts";
import ListItemText from "@mui/material/ListItemText";
import AttributeDefinitionLabel from "../AttributeDefinitionLabel.tsx";
import {IconButton, ListItemSecondaryAction} from "@mui/material";
import {stopPropagation} from "../../../../lib/stdFuncs.ts";
import DeleteIcon from "@mui/icons-material/Delete";
import ListItemButton from "@mui/material/ListItemButton";
import * as React from "react";
import {SortableItemProps} from "../../../Ui/Sortable/SortableList.tsx";
import {AttributeDefinitionsIndex} from "../../../../store/attributeDefinitionStore.ts";

type Props = {
    itemProps: {
        definitionsIndex: AttributeDefinitionsIndex;
        removeItem: (id: string) => void;
    }
} & SortableItemProps<AttributeListItem>;

export default function Item({
    data,
    itemProps: {
        removeItem,
        definitionsIndex,
    }
}: Props) {
    const labelId = `d-${data.id}-label`;
    let def: AttributeDefinition | undefined;
    if (data.type === AttributeListItemType.Definition) {
        def = definitionsIndex[data.definition!];
    } else if (data.type === AttributeListItemType.BuiltIn) {
        def = definitionsIndex[data.key!];
    }

    return <ListItemButton
        role="listitem"
        sx={data.type === AttributeListItemType.Divider ? theme => ({
            backgroundColor: theme.palette.divider,
        }) : undefined}
    >
        <ListItemText
            id={labelId}
            primary={def ? <AttributeDefinitionLabel data={def}/> : data.key}
            secondary={data.id}
        />
        <ListItemSecondaryAction>
            <IconButton
                onMouseDown={stopPropagation}
                onClick={(e) => {
                    e.stopPropagation();
                    removeItem(data.id!);
                }}
            >
                <DeleteIcon/>
            </IconButton>
        </ListItemSecondaryAction>
    </ListItemButton>
}
