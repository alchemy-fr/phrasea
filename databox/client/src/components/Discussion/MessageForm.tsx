import {useTranslation} from 'react-i18next';
import {useFormSubmit} from '@alchemy/api';
import {useFormPrompt} from '@alchemy//navigation';
import {postThreadMessage} from '../../api/discussion.ts';
import {DeserializedMessageAttachment, ThreadMessage} from '../../types.ts';
import React, {useCallback} from 'react';
import {
    AnnotationType,
    AssetAnnotation,
    OnNewAnnotationRef,
} from '../Media/Asset/Annotations/annotationTypes.ts';
import {OnActiveAnnotations} from '../Media/Asset/Attribute/Attributes.tsx';
import MessageField, {MessageFormData} from './MessageField.tsx';

type Props = {
    threadKey: string;
    threadId?: string;
    onNewMessage: (message: ThreadMessage) => void;
    onNewAnnotationRef?: OnNewAnnotationRef;
    onActiveAnnotations: OnActiveAnnotations | undefined;
};

export default function MessageForm({
    threadKey,
    threadId,
    onNewMessage,
    onNewAnnotationRef,
    onActiveAnnotations,
}: Props) {
    const {t} = useTranslation();
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [attachments, setAttachments] = React.useState<
        DeserializedMessageAttachment[]
    >([]);

    React.useEffect(() => {
        if (onNewAnnotationRef) {
            onNewAnnotationRef.current = (annotation: AssetAnnotation) => {
                inputRef.current?.focus();

                const annotationTypes: Record<AnnotationType, string> = {
                    [AnnotationType.Draw]: t('annotation.type.draw', 'Draw'),
                    [AnnotationType.Highlight]: t(
                        'annotation.type.highlight',
                        'Highlight'
                    ),
                    [AnnotationType.Cue]: t('annotation.type.cue', 'Cue'),
                    [AnnotationType.Circle]: t(
                        'annotation.type.circle',
                        'Circle'
                    ),
                    [AnnotationType.Rect]: t(
                        'annotation.type.rectangle',
                        'Rectangle'
                    ),
                    [AnnotationType.Point]: t('annotation.type.point', 'Point'),
                    [AnnotationType.TimeRange]: t(
                        'annotation.type.timerange',
                        'Time Range'
                    ),
                };

                setAttachments(p =>
                    p.concat({
                        type: 'annotation',
                        data: {
                            ...annotation,
                            name:
                                annotation.name ??
                                t('form.annotation.default_name', {
                                    defaultValue: '{{type}} #{{n}}',
                                    type: annotationTypes[annotation.type],
                                    n:
                                        p.filter(
                                            a => a.type === annotation.type
                                        ).length + 1,
                                }),
                        },
                    })
                );
            };
        }
    }, [onNewAnnotationRef, inputRef]);

    const onFocus = useCallback(() => {
        if (onActiveAnnotations) {
            const assetAnnotations = attachments
                .filter(a => a.type === 'annotation')
                .map(a => a.data as AssetAnnotation);
            if (assetAnnotations.length > 0) {
                onActiveAnnotations(assetAnnotations);
            }
        }
    }, [attachments, onActiveAnnotations]);

    React.useEffect(() => {
        inputRef.current?.addEventListener('focus', onFocus);
        onFocus();

        return () => {
            inputRef.current?.removeEventListener('focus', onFocus);
        };
    }, [onFocus, inputRef]);

    const useFormSubmitProps = useFormSubmit<MessageFormData, ThreadMessage>({
        defaultValues: {
            content: '',
        },
        onSubmit: async (data: MessageFormData) => {
            return await postThreadMessage({
                threadId,
                threadKey,
                content: data.content,
                attachments: attachments.map(({data, ...rest}) => ({
                    ...rest,
                    content: JSON.stringify(data),
                })),
            });
        },
        onSuccess: (data: ThreadMessage) => {
            onNewMessage(data);
            setAttachments([]);
            reset();
        },
    });

    const {forbidNavigation, handleSubmit, reset} = useFormSubmitProps;

    useFormPrompt(t, forbidNavigation);

    return (
        <form onSubmit={handleSubmit}>
            <MessageField
                useFormSubmitProps={useFormSubmitProps}
                attachments={attachments}
                setAttachments={setAttachments}
                inputRef={inputRef}
                submitLabel={t('form.thread_message.submit.label', `Send`)}
                placeholder={t(
                    'form.thread_message.content.placeholder',
                    'Type your message here'
                )}
            />
        </form>
    );
}
