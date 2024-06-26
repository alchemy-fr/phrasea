import {AttributeBatchAction, AttributeBatchActionEnum} from "../../api/asset.ts";
import {Avatar, List, ListItem, ListItemAvatar, ListItemText, Typography} from "@mui/material";
import {AttributeDefinitionIndex} from "./types.ts";
import {styled} from "@mui/material/styles";
import NotesIcon from '@mui/icons-material/Notes';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import {getAttributeType} from "../Media/Asset/Attribute/types";
import {AttributeFormatterProps} from "../Media/Asset/Attribute/types/types";
import {useContext} from "react";
import {AttributeFormatContext} from "../Media/Asset/Attribute/Format/AttributeFormatContext.ts";

type Props = {
    actions: AttributeBatchAction[];
    definitionIndex: AttributeDefinitionIndex;
};

export type {Props as ValueDiffProps};

export default function ValueDiff({
    actions,
    definitionIndex,
}: Props) {
    const formatContext = useContext(AttributeFormatContext);

    const actionIcons = {
        [AttributeBatchActionEnum.Delete]: <DeleteIcon/>,
        [AttributeBatchActionEnum.Set]: <NotesIcon/>,
        [AttributeBatchActionEnum.Add]: <AddIcon/>,
        [AttributeBatchActionEnum.Replace]: <NotesIcon/>,
    }

    return <List>
        {actions.map((a, i) => {
            const definition = definitionIndex[a.definitionId!];
            const formatter = getAttributeType(definition.fieldType);
            const valueFormatterProps: AttributeFormatterProps = {
                value: [AttributeBatchActionEnum.Add, AttributeBatchActionEnum.Delete].includes(a.action!) ? [a.value] : a.value,
                locale: a.locale,
                multiple: definition.multiple,
                format: formatContext.formats[definition.fieldType],
            };

            const formatted = formatter.formatValue(valueFormatterProps);

            return <ListItem
                key={i}
                alignItems="flex-start"
            >
                <ListItemAvatar>
                    <Avatar>
                        {actionIcons[a.action!]}
                    </Avatar>
                </ListItemAvatar>
                <ListItemText
                    primary={definition.name}
                    secondary={
                        <>
                            <Typography
                                sx={{display: 'inline'}}
                                component="span"
                                variant="body2"
                                color="text.primary"
                            >
                                {a.action! === AttributeBatchActionEnum.Delete ? <Rem>
                                    {formatted}
                                </Rem> : (a.action === AttributeBatchActionEnum.Add ? <Add>{formatted}</Add> : <Set>{formatted}</Set>)}
                            </Typography>
                            {` — ${a.assets!.length} assets`}
                        </>
                    }
                />
            </ListItem>
        })}
    </List>
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
