import * as React from 'react';
import {ReactNode} from 'react';
import Grid from '@mui/material/Grid';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Checkbox from '@mui/material/Checkbox';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import {AttributeDefinition, AttributeListItem, AttributeListItemType} from "../../../../types.ts";
import {AttributeDefinitionsIndex} from "../../../../store/attributeDefinitionStore.ts";
import AttributeDefinitionLabel from "../AttributeDefinitionLabel.tsx";
import {IconButton, ListItemSecondaryAction, TextField} from "@mui/material";
import DeleteIcon from "@mui/icons-material/Delete";
import {attributeDefinitionToItem, createDivider, hasDefinitionInItems} from "../../../../store/attributeListStore.ts";
import {stopPropagation} from "../../../../lib/stdFuncs.ts";
import {FlexRow} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import SortableList from "../../../Ui/Sortable/SortableList.tsx";
import Item from "./Item.tsx";

type Props = {
    definitions: AttributeDefinition[];
    definitionsIndex: AttributeDefinitionsIndex;
    list: AttributeListItem[];
    onSort: (items: string[]) => void;
    onAdd: (items: AttributeListItem[]) => void;
    onRemove: (items: string[]) => void;
};

export default function AttributeDefinitionTransferList({definitions, definitionsIndex, list, onSort, onAdd, onRemove}: Props) {
    const [checked, setChecked] = React.useState<string[]>([]);
    const [items, setItems] = React.useState<AttributeListItem[]>(list);
    const [query, setQuery] = React.useState('');
    const {t} = useTranslation();

    React.useEffect(() => {
        setItems(list);
    }, [list]);

    const handleToggle = (value: string) => () => {
        const currentIndex = checked.indexOf(value);
        const newChecked = [...checked];

        if (currentIndex === -1) {
            newChecked.push(value);
        } else {
            newChecked.splice(currentIndex, 1);
        }

        setChecked(newChecked);
    };

    const getDefinitionsNotPresent = (items: AttributeListItem[], definitions: string[]): AttributeListItem[] => {
        return definitions.map((id: string) => {
            if (hasDefinitionInItems(items, id)) {
                return undefined;
            }
            const def = definitionsIndex[id];
            if (!def || hasDefinitionInItems(items, id)) {
                return undefined;
            }

            return attributeDefinitionToItem(def);
        }).filter(i => !!i) as AttributeListItem[];
    }

    const handleAddAll = () => {
        const addedItems = getDefinitionsNotPresent(items, definitions.map(d => d.id));
        onAdd(addedItems);
        setItems(items.concat(addedItems));
    };

    const handleAddDivider = () => {
        const key = window.prompt(`What name?`);
        if (key) {
            const addedItems = [createDivider(key)];

            onAdd(addedItems);
            setItems(items.concat(addedItems));
        }
    };
    const handleClear = () => {
        setItems([]);
        onRemove(items.map(i => i.id));
    };

    const toggleAll = () => {
        setChecked(p => {
            if (p.length === 0) {
                return left.map(d => d.id);
            }

            return [];
        });
    };

    const handleAddChecked = () => {
        const addedItems = getDefinitionsNotPresent(items, checked);
        setItems(items.concat(addedItems));
        onAdd(addedItems);
        setChecked([]);
    };

    const removeItem = (id: string) => {
        onRemove([id]);
        setItems(p => p.filter(i => i.id !== id));
    };

    const customList = (children: ReactNode) => (
        <Paper sx={{ width: 300, height: 450, overflow: 'auto' }}>
            <List dense component="div" role="list">
                {children}
            </List>
        </Paper>
    );

    const left = definitions.filter(d => !hasDefinitionInItems(items, d.id));

    const leftList = customList(<>{left
        .filter(d => !query || d.name.toLowerCase().includes(query.toLowerCase()))
        .map((definition: AttributeDefinition) => {
        const labelId = `d-${definition.id}-label`;

        return (
            <ListItemButton
                key={definition.id}
                role="listitem"
                onClick={handleToggle(definition.id)}
            >
                <ListItemIcon>
                    <Checkbox
                        checked={checked.includes(definition.id)}
                        tabIndex={-1}
                        disableRipple
                        inputProps={{
                            'aria-labelledby': labelId,
                        }}
                    />
                </ListItemIcon>
                <ListItemText
                    id={labelId}
                    primary={<AttributeDefinitionLabel data={definition}/>}
                />
            </ListItemButton>
        );
    })}</>);

    const rightList = customList(<SortableList
        onOrderChange={items => {
            setItems(items);
            onSort(items.map(i => i.id));
        }}
        list={items}
        itemProps={{
            removeItem,
            definitionsIndex,
        }}
        itemComponent={Item}
    />);


    return (
        <Grid
            container
            spacing={2}
            sx={{ justifyContent: 'center', alignItems: 'center' }}
        >
            <Grid>
                <FlexRow>
                    <Checkbox
                        checked={checked.length === left.length && left.length > 0}
                        tabIndex={-1}
                        disableRipple
                        onChange={toggleAll}
                        inputProps={{
                            'aria-labelledby': 'checkall',
                        }}
                    />
                    <TextField
                        type={'search'}
                        variant={'standard'}
                        placeholder={t('dialog.search', 'Search')}
                        value={query}
                        onChange={e => setQuery(e.target.value)}
                    />
                </FlexRow>
                {leftList}
            </Grid>
            <Grid>
                <Grid container direction="column" sx={{ alignItems: 'center' }}>
                    <Button
                        sx={{ my: 0.5 }}
                        variant="outlined"
                        size="small"
                        onClick={handleAddAll}
                        disabled={left.length === 0}
                        aria-label="move all right"
                    >
                        ≫
                    </Button>
                    <Button
                        sx={{ my: 0.5 }}
                        variant="outlined"
                        size="small"
                        onClick={handleAddChecked}
                        disabled={checked.length === 0}
                        aria-label="move selected right"
                    >
                        &gt;
                    </Button>
                    <Button
                        sx={{ my: 0.5 }}
                        variant="outlined"
                        size="small"
                        onClick={handleClear}
                        disabled={items.length === 0}
                        aria-label="clear list"
                    >
                        ≪
                    </Button>
                </Grid>
            </Grid>
            <Grid>
                <div>
                    <Button
                        onClick={handleAddDivider}
                    >
                        {t('attribute_list.organize.add_divider', 'Add divider')}
                    </Button>
                </div>
                {rightList}
            </Grid>
        </Grid>
    );
}
