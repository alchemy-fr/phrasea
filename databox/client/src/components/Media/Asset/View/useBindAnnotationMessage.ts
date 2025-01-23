import {
    AnnotationId,
    AssetAnnotation,
    AssetAnnotationRef,
    OnNewAnnotation,
    OnUpdateAnnotation,
} from '../Annotations/annotationTypes.ts';
import {
    AttachmentType,
    MessageFormRef,
} from '../../../Discussion/discussion.ts';
import {useCallback} from 'react';
import {DeserializedMessageAttachment} from '../../../../types.ts';
import {
    OnAttachmentClick,
    OnAttachmentRemove,
} from '../../../Discussion/MessageField.tsx';

type Props = {
    assetAnnotationsRef: AssetAnnotationRef;
    messageFormRef: MessageFormRef;
};

export function useBindAnnotationMessage({
    assetAnnotationsRef,
    messageFormRef,
}: Props) {
    const onAttachmentClick: OnAttachmentClick = useCallback(
        (attachment, attachments) => {
            if (attachment.type === AttachmentType.Annotation) {
                const a = assetAnnotationsRef.current;

                if (a) {
                    a.selectAnnotation(attachment.data as AssetAnnotation);
                    a.replaceAnnotations(
                        attachments
                            .filter(a => a.type === AttachmentType.Annotation)
                            .map(a => a.data as AssetAnnotation)
                    );
                }
            }
        },
        [assetAnnotationsRef]
    );

    const onNewAnnotation: OnNewAnnotation = useCallback(
        (annotation: AssetAnnotation) => {
            messageFormRef.current?.addAttachment({
                id: annotation.id!,
                type: AttachmentType.Annotation,
                data: annotation,
            });
        },
        [messageFormRef]
    );

    const onUpdateAnnotation: OnUpdateAnnotation = useCallback(
        (id: AnnotationId, newAnnotation: AssetAnnotation) => {
            messageFormRef.current?.updateAttachment(id, a => {
                return {
                    ...a,
                    data: newAnnotation,
                };
            });

            return newAnnotation;
        },
        [messageFormRef]
    );

    const onDeleteAnnotation = useCallback(
        (id: AnnotationId) => {
            messageFormRef.current?.removeAttachment(id);
        },
        [messageFormRef]
    );

    const onAttachmentRemove: OnAttachmentRemove = useCallback(
        (attachment: DeserializedMessageAttachment) => {
            if (attachment.type === AttachmentType.Annotation) {
                assetAnnotationsRef.current?.deleteAnnotation(attachment.id!);
            }
        },
        [assetAnnotationsRef]
    );

    const onMessageFocus = useCallback(() => {
        const attachments = messageFormRef.current?.getAttachments();

        assetAnnotationsRef.current?.replaceAnnotations(
            attachments
                ?.filter(a => a.type === AttachmentType.Annotation)
                .map(a => a.data as AssetAnnotation) ?? []
        );
    }, [messageFormRef, assetAnnotationsRef]);

    const onMessageDelete = useCallback(() => {
        assetAnnotationsRef.current?.replaceAnnotations([]);
    }, [assetAnnotationsRef]);

    return {
        onAttachmentClick,
        onNewAnnotation,
        onUpdateAnnotation,
        onDeleteAnnotation,
        onAttachmentRemove,
        onMessageFocus,
        onMessageDelete,
    };
}
