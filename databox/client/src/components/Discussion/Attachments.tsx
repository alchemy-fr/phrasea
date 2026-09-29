import {DeserializedMessageAttachment} from '../../types.ts';
import {Box, Chip} from '@mui/material';
import {OnAttachmentClick} from './MessageField.tsx';
import {AttachmentType} from './discussion.ts';

type Props = {
    attachments: DeserializedMessageAttachment[];
    onDelete?: (attachment: DeserializedMessageAttachment) => void;
    onClick?: OnAttachmentClick;
};

export default function Attachments({attachments, onDelete, onClick}: Props) {
    return (
        <Box
            sx={{
                'p': 1,
                '> *': {
                    display: 'inline-block',
                    mt: 1,
                    mr: 1,
                },
            }}
        >
            {attachments?.map((attachment, index) => {
                // Files attached from the new client: a download link
                const fileUrl: string | undefined =
                    attachment.type === AttachmentType.File
                        ? attachment.data.url
                        : undefined;

                return (
                    <div key={index}>
                        <Chip
                            label={attachment.data.name! ?? 'Attachment'}
                            variant="outlined"
                            {...(fileUrl
                                ? {
                                      component: 'a',
                                      href: fileUrl,
                                      target: '_blank',
                                      rel: 'noreferrer',
                                      clickable: true,
                                  }
                                : {})}
                            onClick={
                                !fileUrl && onClick
                                    ? () => onClick(attachment, attachments)
                                    : undefined
                            }
                            onDelete={
                                onDelete
                                    ? () => onDelete(attachment)
                                    : undefined
                            }
                        />
                    </div>
                );
            })}
        </Box>
    );
}
