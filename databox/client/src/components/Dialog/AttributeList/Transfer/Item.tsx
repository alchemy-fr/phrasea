import {AttributeDefinition, AttributeListItem, AttributeListItemType} from "../../../../types.ts";
import ListItemText from "@mui/material/ListItemText";
import AttributeDefinitionLabel from "../AttributeDefinitionLabel.tsx";
import {IconButton, ListItemIcon, ListItemSecondaryAction} from "@mui/material";
import {stopPropagation} from "../../../../lib/stdFuncs.ts";
import DeleteIcon from "@mui/icons-material/Delete";
import ListItemButton from "@mui/material/ListItemButton";
import * as React from "react";
import {ReactNode} from "react";
import {SortableItemProps} from "../../../Ui/Sortable/SortableList.tsx";
import {AttributeDefinitionsIndex} from "../../../../store/attributeDefinitionStore.ts";
import HeightIcon from "@mui/icons-material/Height";
import HorizontalRuleIcon from "@mui/icons-material/HorizontalRule";

type Props = {
    itemProps: {
        definitionsIndex: AttributeDefinitionsIndex;
        removeItem: (id: string) => void;
        onClick: (data: AttributeListItem) => void;
    }
} & SortableItemProps<AttributeListItem>;

export default function Item({
    data,
    itemProps: {
        removeItem,
        definitionsIndex,
        onClick,
    }
}: Props) {
    const labelId = `d-${data.id}-label`;
    let def: AttributeDefinition | undefined;
    if (data.type === AttributeListItemType.Definition) {
        def = definitionsIndex[data.definition!];
    } else if (data.type === AttributeListItemType.BuiltIn) {
        def = definitionsIndex[data.key!];
    }

    const getLabel  = () => {
        if (data.type === AttributeListItemType.Definition) {
            return <AttributeDefinitionLabel data={def!}/>;
        } else if (data.type === AttributeListItemType.BuiltIn) {
            return <AttributeDefinitionLabel data={def!}/>;
        } else {
            return getItemLabel(data, definitionsIndex);
        }
    }

    let icon: ReactNode | undefined;
    if (data.type === AttributeListItemType.Spacer) {
        icon = <HeightIcon/>;
    } else if (data.type === AttributeListItemType.Divider) {
        icon = <HorizontalRuleIcon/>;
    }

    return <ListItemButton
        role="listitem"
        onClick={() => onClick(data)}
    >
        {icon ? <ListItemIcon>
            {icon}
        </ListItemIcon> : null}
        <ListItemText
            id={labelId}
            primary={getLabel()}
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

export function getItemLabel(item: AttributeListItem, definitionsIndex: AttributeDefinitionsIndex): string {
    if (item.type === AttributeListItemType.Definition || item.type === AttributeListItemType.BuiltIn) {
        const def = definitionsIndex[item.definition!];
        if (def) {
            return def.nameTranslated ?? def.name ?? 'Unknown';
        } else if (item.key) {
            return item.key;
        }
    } else if (item.type === AttributeListItemType.Spacer) {
        return 'Spacer';
    } else if (item.type === AttributeListItemType.Divider) {
        return item.key ?? 'Divider';
    }

    return 'Unknown';
}
