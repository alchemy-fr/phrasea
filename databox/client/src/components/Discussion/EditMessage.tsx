import {useTranslation} from 'react-i18next';
import {useFormSubmit} from '@alchemy/api';
import {useFormPrompt} from '@alchemy//navigation';
import {putThreadMessage} from '../../api/discussion.ts';
import {ThreadMessage} from '../../types.ts';
import React from 'react';
import MessageField, {MessageFormData} from './MessageField.tsx';

type Props = {
    data: ThreadMessage;
    onEdit: (message: ThreadMessage) => void;
    onCancel: () => void;
};

export default function EditMessage({data, onEdit, onCancel}: Props) {
    const {t} = useTranslation();
    const inputRef = React.useRef<HTMLInputElement | null>(null);

    const useFormSubmitProps = useFormSubmit<MessageFormData, ThreadMessage>({
        defaultValues: data,
        onSubmit: async (formData: MessageFormData) => {
            return await putThreadMessage(data.id, {
                content: formData.content,
            });
        },
        onSuccess: (data: ThreadMessage) => {
            onEdit(data);
        },
    });

    React.useEffect(() => {
        inputRef.current?.focus();
    }, []);

    const {handleSubmit, forbidNavigation} = useFormSubmitProps;

    useFormPrompt(t, forbidNavigation);

    return (
        <>
            <form onSubmit={handleSubmit}>
                <MessageField
                    useFormSubmitProps={useFormSubmitProps}
                    inputRef={inputRef}
                    submitLabel={t('form.thread_message.edit.label', `Edit`)}
                    placeholder={t(
                        'form.thread_message.content.placeholder',
                        'Type your message here'
                    )}
                    onCancel={onCancel}
                    cancelButtonLabel={t('common.cancel', `Cancel`)}
                />
            </form>
        </>
    );
}
