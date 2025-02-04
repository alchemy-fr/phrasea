import {useTranslation} from 'react-i18next';
import {useFormSubmit} from '@alchemy/api';
import {useFormPrompt} from '@alchemy//navigation';
import {postThreadMessage} from '../../api/discussion.ts';
import {DeserializedMessageAttachment, ThreadMessage} from '../../types.ts';
import React, {forwardRef, useImperativeHandle} from 'react';
import MessageField, {
    BaseMessageFieldProps,
    MessageFormData,
} from './MessageField.tsx';
import {MessageFormHandle, MessageFormRef} from './discussion.ts';

export type BaseMessageFormProps = {
    messageFormRef?: MessageFormRef;
    normalizeAttachment?: (
        attachment: DeserializedMessageAttachment
    ) => DeserializedMessageAttachment;
} & BaseMessageFieldProps;

type Props = {
    threadKey: string;
    threadId?: string;
    onNewMessage: (message: ThreadMessage) => void;
} & Omit<BaseMessageFormProps, 'messageFormRef'>;

export default forwardRef<MessageFormHandle, Props>(function MessageForm(
    {
        threadKey,
        threadId,
        onNewMessage,
        normalizeAttachment,
        ...messageProps
    }: Props,
    ref
) {
    const {t} = useTranslation();
    const inputRef = React.useRef<HTMLTextAreaElement | null>(null);
    const [attachments, setAttachments] = React.useState<
        DeserializedMessageAttachment[]
    >([]);

    React.useEffect(() => {
        setAttachments([]);
    }, [threadKey]);

    useImperativeHandle(
        ref,
        () =>
            ({
                addAttachment: attachment => {
                    setAttachments(p => p.concat(attachment));
                    setTimeout(() => {
                        inputRef.current?.focus();
                    }, 200);
                },
                removeAttachment: attachmentId =>
                    setAttachments(p => p.filter(a => a.id !== attachmentId)),
                updateAttachment: (attachmentId, handler) => {
                    setAttachments(p =>
                        p.map(a => (a.id === attachmentId ? handler(a) : a))
                    );
                },
                clearAttachments: () => setAttachments([]),
                getAttachments: () => attachments,
            }) as MessageFormHandle,
        [setAttachments, attachments, inputRef]
    );

    const defaultValues = {
        content: '',
    };
    const useFormSubmitProps = useFormSubmit<MessageFormData, ThreadMessage>({
        defaultValues,
        onSubmit: async (data: MessageFormData) => {
            return await postThreadMessage({
                threadId,
                threadKey,
                content: data.content,
                attachments: attachments
                    .map(normalizeAttachment ? normalizeAttachment : a => a)
                    .map(({data, ...rest}) => ({
                        ...rest,
                        content: JSON.stringify({
                            ...data,
                        }),
                    })),
            });
        },
        onSuccess: (data: ThreadMessage) => {
            reset(defaultValues);
            onNewMessage(data);
            setAttachments([]);
        },
    });

    const {forbidNavigation, handleSubmit, reset} = useFormSubmitProps;

    useFormPrompt(t, forbidNavigation || attachments.length > 0);

    return (
        <form onSubmit={handleSubmit}>
            <MessageField
                {...messageProps}
                setAttachments={setAttachments}
                attachments={attachments}
                useFormSubmitProps={useFormSubmitProps}
                inputRef={inputRef}
                submitLabel={t('form.thread_message.submit.label', `Send`)}
                placeholder={t(
                    'form.thread_message.content.placeholder',
                    'Type your message here'
                )}
            />
        </form>
    );
});
