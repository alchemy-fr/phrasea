import {ThreadMessage} from '../../types.ts';
import {
    Box,
    Divider,
    ListItemIcon,
    ListItemText,
    MenuItem,
    Typography,
} from '@mui/material';
import moment from 'moment';
import {OnActiveAnnotations} from '../Media/Asset/Attribute/Attributes.tsx';
import {AssetAnnotation} from '../Media/Asset/Annotations/annotationTypes.ts';
import Attachments from './Attachments.tsx';
import {FlexRow, MoreActionsButton, UserAvatar} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import EditMessage from './EditMessage.tsx';
import React from 'react';
import nl2br from 'react-nl2br';

type Props = {
    message: ThreadMessage;
    onActiveAnnotations?: OnActiveAnnotations | undefined;
    onDelete: (message: ThreadMessage) => void;
    onEdit: (message: ThreadMessage) => void;
};

export default function DiscussionMessage({
    message,
    onActiveAnnotations,
    onDelete,
    onEdit,
}: Props) {
    const m = moment(message.createdAt);
    const {t} = useTranslation();
    const [editing, setEditing] = React.useState(false);

    return (
        <>
            <FlexRow
                style={{
                    alignItems: 'flex-start',
                }}
            >
                <Box
                    sx={{
                        mr: 1,
                    }}
                >
                    <UserAvatar size={40} username={message.author.username} />
                </Box>
                <div
                    style={{
                        flexGrow: 1,
                    }}
                >
                    <div>
                        <FlexRow>
                            <div
                                style={{
                                    flexGrow: 1,
                                }}
                            >
                                <strong>{message.author.username}</strong>
                                <small>
                                    {' - '}
                                    <span title={m.format('LLL')}>
                                        {m.calendar()}
                                    </span>
                                </small>
                            </div>

                            <MoreActionsButton>
                                {closeWrapper => [
                                    message.capabilities?.canEdit ? (
                                        <MenuItem
                                            disableRipple={true}
                                            key={'edit'}
                                            onClick={closeWrapper(() => {
                                                setEditing(true);
                                            })}
                                        >
                                            <ListItemIcon>
                                                <EditIcon />
                                            </ListItemIcon>
                                            <ListItemText
                                                primary={t(
                                                    'common.edit',
                                                    'Edit'
                                                )}
                                            />
                                        </MenuItem>
                                    ) : null,
                                    message.capabilities?.canDelete ? (
                                        <MenuItem
                                            disableRipple={true}
                                            color={'error'}
                                            key={'delete'}
                                            onClick={closeWrapper(() => {
                                                onDelete(message);
                                            })}
                                        >
                                            <ListItemIcon>
                                                <DeleteIcon />
                                            </ListItemIcon>
                                            <ListItemText
                                                primary={t(
                                                    'common.delete',
                                                    'Delete'
                                                )}
                                            />
                                        </MenuItem>
                                    ) : null,
                                ]}
                            </MoreActionsButton>
                        </FlexRow>
                    </div>

                    <>
                        {editing ? (
                            <EditMessage
                                data={message}
                                onEdit={message => {
                                    setEditing(false);
                                    onEdit(message);
                                }}
                                onCancel={() => {
                                    setEditing(false);
                                }}
                            />
                        ) : (
                            <>
                                <Typography>
                                    {nl2br(message.content)}
                                </Typography>

                                {message.attachments ? (
                                    <Attachments
                                        onClick={attachment => {
                                            if (
                                                onActiveAnnotations &&
                                                attachment.type === 'annotation'
                                            ) {
                                                onActiveAnnotations([
                                                    attachment.data as AssetAnnotation,
                                                ]);
                                            }
                                        }}
                                        attachments={message.attachments.map(
                                            a => ({
                                                data: JSON.parse(a.content),
                                                type: a.type,
                                            })
                                        )}
                                    />
                                ) : null}
                            </>
                        )}
                    </>
                </div>
            </FlexRow>
            <Divider
                sx={{
                    mt: 1,
                    mb: 2,
                }}
            />
        </>
    );
}
