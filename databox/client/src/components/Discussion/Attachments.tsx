import {DeserializedMessageAttachment} from '../../types.ts';
import {Box, Chip} from '@mui/material';
import {OnAttachmentClick} from './MessageField.tsx';

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
                return (
                    <div key={index}>
                        <Chip
                            label={attachment.data.name! ?? 'Attachment'}
                            variant="outlined"
                            onClick={
                                onClick
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
