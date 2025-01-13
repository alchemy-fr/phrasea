import {Box, Button, InputBase} from "@mui/material";
import {useTranslation} from 'react-i18next';
import {useFormSubmit} from '@alchemy/api';
import {useFormPrompt} from "@alchemy//navigation";
import {FormFieldErrors, FormRow} from "@alchemy//react-form";
import {postThreadMessage} from "../../api/discussion.ts";
import {DeserializedMessageAttachment, ThreadMessage} from "../../types.ts";
import RemoteErrors from "../Form/RemoteErrors.tsx";
import {LoadingButton} from "@mui/lab";
import SendIcon from '@mui/icons-material/Send';
import React from "react";
import {AnnotationType, AssetAnnotation, OnNewAnnotationRef} from "../Media/Asset/Annotations/annotationTypes.ts";
import {OnActiveAnnotations} from "../Media/Asset/Attribute/Attributes.tsx";
import {FlexRow} from "@alchemy/phrasea-ui";
import Attachments from "./Attachments.tsx";

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
    const [attachments, setAttachments] = React.useState<DeserializedMessageAttachment[]>([]);

    React.useEffect(() => {
        if (onNewAnnotationRef) {
            onNewAnnotationRef.current = (annotation: AssetAnnotation) => {
                inputRef.current?.focus();

                const annotationTypes: Record<AnnotationType, string> = {
                    [AnnotationType.Draw]: t('annotation.type.draw', 'Draw'),
                    [AnnotationType.Highlight]: t('annotation.type.highlight', 'Highlight'),
                    [AnnotationType.Cue]: t('annotation.type.cue', 'Cue'),
                    [AnnotationType.Circle]: t('annotation.type.circle', 'Circle'),
                    [AnnotationType.Rect]: t('annotation.type.rectangle', 'Rectangle'),
                    [AnnotationType.Point]: t('annotation.type.point', 'Point'),
                    [AnnotationType.TimeRange]: t('annotation.type.timerange', 'Time Range'),
                };

                setAttachments(p => p.concat({
                    type: 'annotation',
                    data: {
                        ...annotation,
                        name: annotation.name ?? t('form.annotation.default_name', {
                            defaultValue: '{{type}} #{{n}}',
                            type: annotationTypes[annotation.type],
                            n: p.filter(a => a.type === annotation.type).length + 1,
                        }),
                    },
                }));
            }
        }
    }, [onNewAnnotationRef, inputRef]);


    React.useEffect(() => {
        if (onActiveAnnotations) {
            onActiveAnnotations(attachments.filter(a => a.type === 'annotation').map(a => a.data as AssetAnnotation));
        }
    }, [attachments]);

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
                attachments: attachments.map(({
                    data,
                    ...rest
                }) => ({
                    ...rest,
                    content: JSON.stringify(data),
                })),
            });
        },
        onSuccess: (data: ThreadMessage) => {
            onNewMessage(data);
            resetAll();
        },
    });
    useFormPrompt(t, forbidNavigation);

    const resetAll = () => {
        setAttachments([]);
        reset();
    }

    return <>
        <form onSubmit={handleSubmit}>
            <FormRow>
                <Box
                    sx={theme => ({
                        border: `1px solid ${theme.palette.divider}`,
                        borderRadius: theme.shape.borderRadius / 4,
                        alignItems: 'center'
                    })}
                    onClick={() => inputRef.current?.focus()}
                >
                    <InputBase
                        sx={{p: 1}}
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
                    <Attachments
                        attachments={attachments}
                        onDelete={a => {
                            setAttachments(p => p.filter(att => att !== a));
                        }}
                    />
                    <FlexRow>
                        <div style={{
                            flexGrow: 1,
                        }}></div>
                        <LoadingButton
                            type="submit"
                            disabled={submitting}
                            loading={submitting}
                            endIcon={<SendIcon/>}
                        >
                            {t('form.thread_message.submit.label', `Send`)}
                        </LoadingButton>
                    </FlexRow>
                </Box>
                <FormFieldErrors field={'content'} errors={errors}/>
            </FormRow>

            <RemoteErrors errors={remoteErrors}/>

            <FormRow>
                <Button
                    onClick={() => resetAll()}>Reset</Button>
            </FormRow>
        </form>
    </>
}
