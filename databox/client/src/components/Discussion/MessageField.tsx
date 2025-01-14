import {Box, Button, InputBase} from "@mui/material";
import {LoadingButton} from "@mui/lab";
import SendIcon from "@mui/icons-material/Send";
import React from "react";
import RemoteErrors from "../Form/RemoteErrors.tsx";
import Attachments from "./Attachments.tsx";
import {DeserializedMessageAttachment, StateSetter, ThreadMessage} from "../../types.ts";
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import type {UseFormSubmitReturn} from '@alchemy/api';
import {FlexRow} from '@alchemy/phrasea-ui';

export type MessageFormData = Pick<ThreadMessage, "content">;

type Props = {
    submitLabel: string;
    placeholder: string;
    inputRef: React.RefObject<HTMLInputElement | null>;
    attachments?: DeserializedMessageAttachment[];
    setAttachments?: StateSetter<DeserializedMessageAttachment[]>;
    useFormSubmitProps: UseFormSubmitReturn<MessageFormData, ThreadMessage>;
    onCancel?: () => void;
    cancelButtonLabel?: string;
};

export default function MessageField({
    submitLabel,
    inputRef,
    attachments,
    setAttachments,
    useFormSubmitProps,
    placeholder,
    onCancel,
    cancelButtonLabel,
}: Props) {
    const {
        formState: {errors},
        remoteErrors,
        submitting,
        register,
    } = useFormSubmitProps;

    return <>
        <FormRow>
            <Box
                sx={theme => ({
                    border: `1px solid ${theme.palette.divider}`,
                    borderRadius: theme.shape.borderRadius / 4,
                    alignItems: 'center',
                })}
                onClick={() => inputRef.current?.focus()}
            >
                <InputBase
                    sx={{p: 1}}
                    required={true}
                    placeholder={placeholder}
                    disabled={submitting}
                    multiline={true}
                    fullWidth={true}
                    {...register('content', {
                        required: true,
                    })}
                    inputRef={inputRef}
                />
                {attachments ? <Attachments
                    attachments={attachments}
                    onDelete={a => {
                        setAttachments!(p => p.filter(att => att !== a));
                    }}
                /> : null}
                <FlexRow>
                    <div
                        style={{
                            flexGrow: 1,
                        }}
                    >

                    </div>
                    <div>
                        {onCancel ? <Button
                            disabled={submitting}
                            onClick={onCancel}
                        >
                            {cancelButtonLabel!}
                        </Button> : null}
                        <LoadingButton
                            type="submit"
                            disabled={submitting}
                            loading={submitting}
                            endIcon={<SendIcon/>}
                        >
                            {submitLabel}
                        </LoadingButton>
                    </div>
                </FlexRow>
            </Box>
            <FormFieldErrors field={'content'} errors={errors}/>
        </FormRow>
        <RemoteErrors errors={remoteErrors}/>
    </>
}
