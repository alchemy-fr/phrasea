import {Box, Button, Chip, InputBase} from "@mui/material";
import {useTranslation} from 'react-i18next';
import {useFormSubmit} from '@alchemy/api';
import {useFormPrompt} from "@alchemy//navigation";
import {FormFieldErrors, FormRow} from "@alchemy//react-form";
import {postThreadMessage} from "../../api/discussion.ts";
import {ThreadMessage} from "../../types.ts";
import RemoteErrors from "../Form/RemoteErrors.tsx";
import {LoadingButton} from "@mui/lab";
import SendIcon from '@mui/icons-material/Send';
import React from "react";
import {AnnotationType, AssetAnnotation, OnNewAnnotationRef} from "../Media/Asset/Annotations/annotationTypes.ts";
import {OnActiveAnnotations} from "../Media/Asset/Attribute/Attributes.tsx";
import {FlexRow} from "@alchemy/phrasea-ui";

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

                const annotationTypes: Record<AnnotationType, string> = {
                    [AnnotationType.Draw]: t('annotation.type.draw', 'Draw'),
                    [AnnotationType.Highlight]: t('annotation.type.highlight', 'Highlight'),
                    [AnnotationType.Cue]: t('annotation.type.cue', 'Cue'),
                    [AnnotationType.Circle]: t('annotation.type.circle', 'Circle'),
                    [AnnotationType.Rect]: t('annotation.type.rectangle', 'Rectangle'),
                    [AnnotationType.Point]: t('annotation.type.point', 'Point'),
                    [AnnotationType.TimeRange]: t('annotation.type.timerange', 'Time Range'),
                };

                setAnnotations(p => {
                    return p.concat({
                        ...annotation,
                        name: annotation.name ?? t('form.annotation.default_name', {
                            defaultValue: '{{type}} #{{n}}',
                            type: annotationTypes[annotation.type],
                            n: p.filter(a => a.type === annotation.type).length + 1,
                        }),
                    });
                });
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
                attachments: annotations.map(a => ({
                    type: 'annotation',
                    content: JSON.stringify(a),
                })),
            });
        },
        onSuccess: (data: ThreadMessage) => {
            onNewMessage(data);
            reset();
            setAnnotations([]);
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
                    <Box sx={{
                        p: 1,
                        '> *': {
                            display: 'inline-block',
                            mt: 1,
                            mr: 1,
                        },
                    }}>
                        {annotations?.map((annotation, index) => (
                            <div key={index}>
                                <Chip
                                    label={annotation.name!}
                                    variant="outlined"
                                    onDelete={() => setAnnotations(p => p.filter((_, i) => i !== index))}
                                />
                            </div>
                        ))}
                    </Box>
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
