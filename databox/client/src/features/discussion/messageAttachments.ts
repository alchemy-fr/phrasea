import type {AssetAnnotation, MessageAttachment} from '@/types/api';
import type {MultipartUpload} from '@/lib/api/upload';

/**
 * Message attachments, shared with the API (`MessageAttachmentManager`):
 * `{type, content}` where `content` is JSON. A file is posted as `{type: 'file', multipart}` (a completed multipart
 * upload that the API turns into a `File`), stored as
 * `{type: 'file', content: '{"id", "name", "type", "size"}'}` and served with
 * a signed `url` added to the content.
 *
 * An annotation (a shape drawn over the asset, see `features/annotations`)
 * is posted and stored as is: `{type: 'annotation', content: '<the shape>'}`.
 */
export const FILE_ATTACHMENT = 'file';
export const ANNOTATION_ATTACHMENT = 'annotation';

export type FileAttachment = {
    id?: string;
    name: string;
    type?: string;
    size?: number;
    url?: string;
};

export type FileAttachmentInput = {
    type: typeof FILE_ATTACHMENT;
    multipart: MultipartUpload;
};

export type AnnotationAttachmentInput = {
    type: typeof ANNOTATION_ATTACHMENT;
    content: string;
};

export type MessageAttachmentInput =
    | FileAttachmentInput
    | AnnotationAttachmentInput;

export function getFileAttachments(
    attachments: MessageAttachment[] | null | undefined
): FileAttachment[] {
    return (attachments ?? []).flatMap(a => {
        if (a?.type !== FILE_ATTACHMENT) {
            return [];
        }
        try {
            const data = JSON.parse(a.content);

            return data && typeof data === 'object'
                ? [{...data, name: String(data.name ?? '')}]
                : [];
        } catch {
            return [];
        }
    });
}

export function isImage(file: {type?: string}): boolean {
    return !!file.type?.startsWith('image/');
}

export function getAnnotationAttachments(
    attachments: MessageAttachment[] | null | undefined
): AssetAnnotation[] {
    return (attachments ?? []).flatMap(a => {
        if (a?.type !== ANNOTATION_ATTACHMENT) {
            return [];
        }
        try {
            const data = JSON.parse(a.content);

            return data &&
                typeof data === 'object' &&
                typeof data.type === 'string'
                ? [data as AssetAnnotation]
                : [];
        } catch {
            return [];
        }
    });
}

export function toAnnotationAttachment(
    annotation: AssetAnnotation
): AnnotationAttachmentInput {
    return {
        type: ANNOTATION_ATTACHMENT,
        content: JSON.stringify(annotation),
    };
}
