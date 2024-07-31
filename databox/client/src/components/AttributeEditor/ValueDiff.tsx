import {
    AttributeBatchAction,
    AttributeBatchActionEnum,
} from '../../api/asset.ts';
import {
    Avatar,
    List,
    ListItem,
    ListItemAvatar,
    ListItemText,
    Typography,
} from '@mui/material';
import {AttributeDefinitionIndex} from './types.ts';
import {styled} from '@mui/material/styles';
import NotesIcon from '@mui/icons-material/Notes';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import {getAttributeType} from '../Media/Asset/Attribute/types';
import {AttributeFormatterProps} from '../Media/Asset/Attribute/types/types';
import {useContext} from 'react';
import {AttributeFormatContext} from '../Media/Asset/Attribute/Format/AttributeFormatContext.ts';

type Props = {
    actions: AttributeBatchAction[];
    definitionIndex: AttributeDefinitionIndex;
};

export type {Props as ValueDiffProps};

export default function ValueDiff({actions, definitionIndex}: Props) {
    const formatContext = useContext(AttributeFormatContext);

    const actionIcons = {
        [AttributeBatchActionEnum.Delete]: <DeleteIcon />,
        [AttributeBatchActionEnum.Set]: <NotesIcon />,
        [AttributeBatchActionEnum.Add]: <AddIcon />,
        [AttributeBatchActionEnum.Replace]: <NotesIcon />,
    };

    const indexedActions: Record<string, AttributeBatchAction[]> = {};
    actions.forEach(a => {
        indexedActions[a.definitionId!] ??= [];
        indexedActions[a.definitionId!].push(a);
    });

    return (
        <List>
            {Object.keys(indexedActions).map(defId => {
                const defActions = indexedActions[defId];
                const definition = definitionIndex[defId];
                const formatter = getAttributeType(definition.fieldType);

                return (
                    <ListItem key={defId} alignItems="flex-start">
                        <ListItemText
                            primary={definition.name}
                            secondaryTypographyProps={{
                                component: 'div',
                            }}
                            secondary={
                                <List>
                                    {defActions.map((a, i) => {
                                        const valueFormatterProps: AttributeFormatterProps =
                                            {
                                                value: a.value,
                                                locale: a.locale,
                                                format: formatContext.formats[
                                                    definition.fieldType
                                                ],
                                            };

                                        const formatted =
                                            formatter.formatValue(
                                                valueFormatterProps
                                            );

                                        return (
                                            <ListItem key={i}>
                                                <ListItemAvatar>
                                                    <Avatar>
                                                        {actionIcons[a.action!]}
                                                    </Avatar>
                                                </ListItemAvatar>
                                                <ListItemText
                                                    secondaryTypographyProps={{
                                                        component: 'span',
                                                    }}
                                                    secondary={
                                                        <>
                                                            <Typography
                                                                sx={{
                                                                    display:
                                                                        'inline',
                                                                }}
                                                                component="span"
                                                                variant="body2"
                                                            >
                                                                {a.action! ===
                                                                AttributeBatchActionEnum.Delete ? (
                                                                    <Rem>
                                                                        {
                                                                            formatted
                                                                        }
                                                                    </Rem>
                                                                ) : a.action ===
                                                                  AttributeBatchActionEnum.Add ? (
                                                                    <Add>
                                                                        {
                                                                            formatted
                                                                        }
                                                                    </Add>
                                                                ) : (
                                                                    <Set>
                                                                        {
                                                                            formatted
                                                                        }
                                                                    </Set>
                                                                )}
                                                            </Typography>
                                                            {` — ${a.assets!.length} assets`}
                                                        </>
                                                    }
                                                />
                                            </ListItem>
                                        );
                                    })}
                                </List>
                            }
                        />
                    </ListItem>
                );
            })}
        </List>
    );
}

const Rem = styled('span')(({theme}) => ({
    textDecoration: 'line-through',
    color: theme.palette.error.main,
}));

const Set = styled('span')(({theme}) => ({
    color: theme.palette.info.main,
}));

const Add = styled('span')(({theme}) => ({
    color: theme.palette.success.main,
}));
