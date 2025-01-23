import {DeserializedMessageAttachment} from '../../types.ts';
import {MutableRefObject} from 'react';

export type MessageFormRef = MutableRefObject<MessageFormHandle | null>;

export type MessageFormHandle = {
    addAttachment: (attachment: DeserializedMessageAttachment) => void;
    removeAttachment: (attachmentId: string) => void;
    updateAttachment: (
        attachmentId: string,
        handler: UpdateAttachmentHandler
    ) => void;
    clearAttachments: () => void;
    getAttachments: () => DeserializedMessageAttachment[];
};

type UpdateAttachmentHandler = (
    attachment: DeserializedMessageAttachment
) => DeserializedMessageAttachment;

export enum AttachmentType {
    Annotation = 'annotation',
    File = 'file',
}
