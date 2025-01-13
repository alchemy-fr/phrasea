import {ThreadMessage} from "../../types.ts";
import {Box, Button, Divider, Typography} from "@mui/material";
import moment from "moment";
import {OnActiveAnnotations} from "../Media/Asset/Attribute/Attributes.tsx";
import {AssetAnnotation} from "../Media/Asset/Annotations/annotationTypes.ts";
import Attachments from "./Attachments.tsx";
import {FlexRow, UserAvatar} from '@alchemy/phrasea-ui'
import {useTranslation} from 'react-i18next';

type Props = {
    message: ThreadMessage;
    onActiveAnnotations?: OnActiveAnnotations | undefined;
    onDelete: (message: ThreadMessage) => void;
};

export default function DiscussionMessage({
    message,
    onActiveAnnotations,
    onDelete,
}: Props) {
    const m = moment(message.createdAt);
    const {t} = useTranslation();

    return <>
        <FlexRow
            style={{
                alignItems: 'flex-start',
            }}
        >
            <Box sx={{
                mr: 1,
            }}>
                <UserAvatar
                    size={40}
                    username={message.author.username}
                />
            </Box>
            <div>
                <div>
                    <small>
                        <strong>
                            {message.author.username}
                        </strong>
                        {' - '}
                        <span title={m.format('LLL')}>
                {m.calendar()}
                    </span>
                    </small>
                    {message.capabilities?.canDelete ? <>
                        {' - '}
                        <Button
                            color={'error'}
                            size={'small'}
                            variant={'text'}
                           onClick={() => {
                            onDelete(message);
                        }}>
                            {t('common.delete', 'Delete')}
                        </Button>
                    </> : null}
                </div>
                <Typography>{message.content}</Typography>

                {message.attachments ? <Attachments
                    onClick={(attachment) => {
                        if (onActiveAnnotations && attachment.type === 'annotation') {
                            onActiveAnnotations([attachment.data as AssetAnnotation]);
                        }
                    }}
                    attachments={message.attachments.map(a => ({
                        data: JSON.parse(a.content),
                        type: a.type,
                    }))}/> : null}
            </div>
        </FlexRow>
        <Divider sx={{
            mt: 1,
            mb: 2,
        }}/>
    </>
}
