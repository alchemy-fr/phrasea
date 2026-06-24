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
import {
    AttributeFormatterOptions,
    AttributeFormatterProps,
} from '../Media/Asset/Attribute/types/types';
import React, {useContext} from 'react';
import {AttributeFormatContext} from '../Media/Asset/Attribute/Format/AttributeFormatContext.ts';
import {
    AttributeBatchAction,
    AttributeBatchActionEnum,
} from '../../api/types.ts';
import {useTranslation} from 'react-i18next';
import {isNoLocale, NO_LOCALE} from '../Media/Asset/Attribute/constants.ts';
import Flag from '../Ui/Flag.tsx';
import {FlexRow} from '@alchemy/phrasea-ui';

type Props = {
    actions: AttributeBatchAction[];
    definitionIndex: AttributeDefinitionIndex;
};

export type {Props as ValueDiffProps};

export default function ValueDiff({actions, definitionIndex}: Props) {
    const formatContext = useContext(AttributeFormatContext);
    const {t, i18n} = useTranslation();

    const actionIcons = {
        [AttributeBatchActionEnum.Delete]: <DeleteIcon />,
        [AttributeBatchActionEnum.Set]: <NotesIcon />,
        [AttributeBatchActionEnum.Add]: <AddIcon />,
        [AttributeBatchActionEnum.Replace]: <NotesIcon />,
    };

    const humanLocale = (l: string) =>
        l === NO_LOCALE ? t('editor_panel.untranslated', `Untranslated`) : l;

    const indexedActions: Record<string, AttributeBatchAction[]> = {};
    actions.forEach(a => {
        indexedActions[a.definitionId!] ??= [];
        indexedActions[a.definitionId!].push(a);
    });

    const formatterOptions: AttributeFormatterOptions = {
        uiLocale: i18n.language,
        t,
    };

    return (
        <List>
            {Object.keys(indexedActions).map(defId => {
                const defActions = indexedActions[defId];
                const definition = definitionIndex[defId];
                const formatter = getAttributeType(definition.type);

                return (
                    <ListItem key={defId} alignItems="flex-start">
                        <ListItemText
                            primary={definition.displayName ?? definition.name}
                            secondaryTypographyProps={{
                                component: 'div',
                            }}
                            secondary={
                                <List>
                                    {defActions.map((a, i) => {
                                        const valueFormatterProps: AttributeFormatterProps =
                                            {
                                                ...formatterOptions,
                                                value: a.value,
                                                locale: a.locale,
                                                format: formatContext.getFormat(
                                                    definition.type,
                                                    definition.id
                                                ),
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
                                                        <FlexRow gap={2}>
                                                            <Typography
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
                                                            {!isNoLocale(
                                                                a.locale
                                                            ) && (
                                                                <FlexRow
                                                                    gap={1}
                                                                >
                                                                    <Flag
                                                                        locale={
                                                                            a.locale!
                                                                        }
                                                                        sx={{
                                                                            mb: 1,
                                                                        }}
                                                                    />
                                                                    {humanLocale(
                                                                        a.locale!
                                                                    )}
                                                                </FlexRow>
                                                            )}
                                                            <div>
                                                                {`— ${a.assets!.length} assets`}
                                                            </div>
                                                        </FlexRow>
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
