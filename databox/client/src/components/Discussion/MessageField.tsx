import {Box, Button, InputBase} from '@mui/material';
import {LoadingButton} from '@mui/lab';
import SendIcon from '@mui/icons-material/Send';
import React from 'react';
import RemoteErrors from '../Form/RemoteErrors.tsx';
import Attachments from './Attachments.tsx';
import {
    DeserializedMessageAttachment,
    StateSetter,
    ThreadMessage,
} from '../../types.ts';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import type {UseFormSubmitReturn} from '@alchemy/api';
import {FlexRow} from '@alchemy/phrasea-ui';
import EmojiPicker from './EmojiPicker.tsx';

export type MessageFormData = Pick<ThreadMessage, 'content'>;

type Props = {
    submitLabel: string;
    placeholder: string;
    inputRef: React.MutableRefObject<HTMLInputElement | null>;
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

    const {ref, ...rest} = register('content', {
        required: true,
    });

    return (
        <>
            <FormRow>
                <Box
                    sx={theme => {
                        return {
                            border: `1px solid ${theme.palette.divider}`,
                            borderRadius: Math.min(
                                theme.shape.borderRadius / 4,
                                1
                            ),
                            alignItems: 'center',
                        };
                    }}
                    onClick={() => inputRef.current?.focus()}
                >
                    <InputBase
                        sx={{p: 1}}
                        required={true}
                        placeholder={placeholder}
                        disabled={submitting}
                        multiline={true}
                        fullWidth={true}
                        {...rest}
                        inputRef={r => {
                            ref(r);
                            inputRef.current = r;
                        }}
                    />
                    {attachments ? (
                        <Attachments
                            attachments={attachments}
                            onDelete={a => {
                                setAttachments!(p =>
                                    p.filter(att => att !== a)
                                );
                            }}
                        />
                    ) : null}
                    <FlexRow>
                        <div
                            style={{
                                flexGrow: 1,
                            }}
                        >
                            <EmojiPicker
                                onSelect={(emoji: string) => {
                                    inputRef.current?.focus();
                                    document.execCommand(
                                        'insertText',
                                        false,
                                        emoji
                                    );
                                }}
                            />
                        </div>

                        <div>
                            {onCancel ? (
                                <Button
                                    disabled={submitting}
                                    onClick={onCancel}
                                >
                                    {cancelButtonLabel!}
                                </Button>
                            ) : null}
                            <LoadingButton
                                type="submit"
                                disabled={submitting}
                                loading={submitting}
                                color={'primary'}
                                endIcon={<SendIcon />}
                            >
                                {submitLabel}
                            </LoadingButton>
                        </div>
                    </FlexRow>
                </Box>
                <FormFieldErrors field={'content'} errors={errors} />
            </FormRow>
            <RemoteErrors errors={remoteErrors} />
        </>
    );
}
