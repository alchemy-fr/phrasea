import {TextField} from "@mui/material";
import {useTranslation} from 'react-i18next';
import {useFormSubmit} from '@alchemy/api';
import {useFormPrompt} from "../../../../../lib/js/navigation";
import {FormFieldErrors, FormRow} from "../../../../../lib/js/react-form";
import {postThreadMessage} from "../../api/discussion.ts";
import {ThreadMessage} from "../../types.ts";
import RemoteErrors from "../Form/RemoteErrors.tsx";
import {LoadingButton} from "@mui/lab";
import SendIcon from '@mui/icons-material/Send';

type Props = {
    threadKey: string;
    threadId?: string;
    onNewMessage: (message: ThreadMessage) => void;
};


export default function MessageForm({
    threadKey,
    threadId,
    onNewMessage,
}: Props) {
    const {t} = useTranslation();

    const {
        formState: {errors},
        handleSubmit,
        remoteErrors,
        submitting,
        register,
        reset,
        forbidNavigation,
    } = useFormSubmit({
        defaultValues: {
            content: '',
        },
        onSubmit: async (data: ThreadMessage) => {
            return await postThreadMessage({
                threadId,
                threadKey,
                content: data.content,
            });
        },
        onSuccess: (data: ThreadMessage) => {
            onNewMessage(data);
            reset();
        },
    });
    useFormPrompt(t, forbidNavigation);

    return <>
        <form onSubmit={handleSubmit}>
            <FormRow>
                <TextField
                    autoFocus
                    required={true}
                    placeholder={t('form.thread_message.content.placeholder', 'Type your message here')}
                    disabled={submitting}
                    multiline={true}
                    fullWidth={true}
                    {...register('content', {
                        required: true,
                    })}
                />
                <FormFieldErrors field={'content'} errors={errors}/>
            </FormRow>

            <RemoteErrors errors={remoteErrors}/>

            <FormRow>
                <LoadingButton
                    variant="contained"
                    type="submit"
                    disabled={submitting}
                    loading={submitting}
                    endIcon={<SendIcon />}
                >
                    {t('form.thread_message.submit.label', `Send`)}
                </LoadingButton>
            </FormRow>
        </form>
    </>
}
