import {
    AttributeDefinition,
    ProfileItem,
    ProfileItemType,
} from '../../../../types.ts';
import ListItemText from '@mui/material/ListItemText';
import AttributeDefinitionLabel from '../AttributeDefinitionLabel.tsx';
import {IconButton, ListItemIcon, ListItemSecondaryAction} from '@mui/material';
import {stopPropagation} from '../../../../lib/stdFuncs.ts';
import DeleteIcon from '@mui/icons-material/Delete';
import ListItemButton from '@mui/material/ListItemButton';
import * as React from 'react';
import {ReactNode} from 'react';
import {SortableItemProps} from '../../../Ui/Sortable/SortableList.tsx';
import {AttributeDefinitionsIndex} from '../../../../store/attributeDefinitionStore.ts';
import HeightIcon from '@mui/icons-material/Height';
import HorizontalRuleIcon from '@mui/icons-material/HorizontalRule';

type Props = {
    itemProps: {
        definitionsIndex: AttributeDefinitionsIndex;
        removeItem: (id: string) => void;
        onClick: (data: ProfileItem) => void;
        selectedItem?: string;
    };
} & SortableItemProps<ProfileItem>;

export default function Item({
    data,
    itemProps: {removeItem, definitionsIndex, onClick, selectedItem},
}: Props) {
    const labelId = `d-${data.id}-label`;
    let def: AttributeDefinition | undefined;
    if (data.type === ProfileItemType.Definition) {
        def = definitionsIndex[data.definition!];
    } else if (data.type === ProfileItemType.BuiltIn) {
        def = definitionsIndex[data.key!];
    }

    const getLabel = () => {
        if (data.type === ProfileItemType.Definition) {
            if (!def) {
                return;
            }

            return <AttributeDefinitionLabel data={def!} />;
        } else if (data.type === ProfileItemType.BuiltIn) {
            if (!def) {
                return;
            }
            return <AttributeDefinitionLabel data={def!} />;
        } else {
            return getItemLabel(data, definitionsIndex);
        }
    };

    let icon: ReactNode | undefined;
    if (data.type === ProfileItemType.Spacer) {
        icon = <HeightIcon />;
    } else if (data.type === ProfileItemType.Divider) {
        icon = <HorizontalRuleIcon />;
    }

    const label = getLabel();
    if (!label) {
        return null;
    }

    return (
        <ListItemButton
            role="listitem"
            onClick={() => onClick(data)}
            selected={selectedItem === data.id}
        >
            {icon ? <ListItemIcon>{icon}</ListItemIcon> : null}
            <ListItemText id={labelId} primary={label} />
            <ListItemSecondaryAction>
                <IconButton
                    onMouseDown={stopPropagation}
                    onClick={e => {
                        e.stopPropagation();
                        removeItem(data.id!);
                    }}
                >
                    <DeleteIcon />
                </IconButton>
            </ListItemSecondaryAction>
        </ListItemButton>
    );
}

export function getItemLabel(
    item: ProfileItem,
    definitionsIndex: AttributeDefinitionsIndex
): string {
    if (
        item.type === ProfileItemType.Definition ||
        item.type === ProfileItemType.BuiltIn
    ) {
        const def = definitionsIndex[item.definition!];
        if (def) {
            return def.nameTranslated ?? def.name ?? 'Unknown';
        } else if (item.key) {
            return item.key;
        }
    } else if (item.type === ProfileItemType.Spacer) {
        return 'Spacer';
    } else if (item.type === ProfileItemType.Divider) {
        return item.key ?? 'Divider';
    }

    return 'Unknown';
}
