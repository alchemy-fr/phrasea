import {Button, TextField} from "@mui/material";
import {useTranslation} from 'react-i18next';
import {useFormSubmit} from '@alchemy/api';
import {useFormPrompt} from "../../../../../lib/js/navigation";
import {FormFieldErrors, FormRow} from "../../../../../lib/js/react-form";
import {postThreadMessage} from "../../api/discussion.ts";
import {ThreadMessage} from "../../types.ts";
import RemoteErrors from "../Form/RemoteErrors.tsx";
import {LoadingButton} from "@mui/lab";
import SendIcon from '@mui/icons-material/Send';
import React from "react";
import {AssetAnnotation, OnNewAnnotationRef} from "../Media/Asset/Annotations/annotationTypes.ts";
import {OnActiveAnnotations} from "../Media/Asset/Attribute/Attributes.tsx";

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
    const [annotations, setAnnotations] = React.useState<AssetAnnotation[]>([]);

    React.useEffect(() => {
        if (onNewAnnotationRef) {
            onNewAnnotationRef.current = (annotation: AssetAnnotation) => {
                inputRef.current?.focus();
                setAnnotations(p => p.concat(annotation));
            }
        }
    }, [onNewAnnotationRef, inputRef]);


    React.useEffect(() => {
        if (onActiveAnnotations) {
            onActiveAnnotations(annotations);
        }
    }, [annotations]);

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

    const resetAll = () => {
        setAnnotations([]);
        reset();
    }

    return <>
        <form onSubmit={handleSubmit}>
            <FormRow>
                <TextField
                    required={true}
                    placeholder={t('form.thread_message.content.placeholder', 'Type your message here')}
                    disabled={submitting}
                    multiline={true}
                    fullWidth={true}
                    {...register('content', {
                        required: true,
                    })}
                    inputRef={inputRef}
                />
                <FormFieldErrors field={'content'} errors={errors}/>
            </FormRow>

            {annotations?.map((annotation, index) => (
                <div key={index}>
                    {annotation.type.toString()}
                </div>
            ))}

            <RemoteErrors errors={remoteErrors}/>

            <FormRow>
                <LoadingButton
                    variant="contained"
                    type="submit"
                    disabled={submitting}
                    loading={submitting}
                    endIcon={<SendIcon/>}
                >
                    {t('form.thread_message.submit.label', `Send`)}
                </LoadingButton>
            </FormRow>

            <FormRow>
                <Button
                    onClick={() => resetAll()}>Reset</Button>
            </FormRow>
        </form>
    </>
}
