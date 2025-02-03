import {alpha, Box, Button, useTheme} from '@mui/material';
import {LoadingButton} from '@mui/lab';
import SendIcon from '@mui/icons-material/Send';
import React from 'react';
import RemoteErrors from '../Form/RemoteErrors.tsx';
import Attachments from './Attachments.tsx';
import {DeserializedMessageAttachment, StateSetter, ThreadMessage,} from '../../types.ts';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import type {UseFormSubmitReturn} from '@alchemy/api';
import {FlexRow} from '@alchemy/phrasea-ui';
import EmojiPicker from './EmojiPicker.tsx';
import MentionTextarea, {BaseMessageInputProps} from "./MentionTextarea.tsx";
import {MentionsSuggestionsStyle} from "react-mentions";
import {Controller} from "react-hook-form";
import {createUserTagStyle} from "./formatMessage.tsx";

export type MessageFormData = Pick<ThreadMessage, 'content'>;

export type OnAttachmentClick = (
    attachment: DeserializedMessageAttachment,
    attachments: DeserializedMessageAttachment[]
) => void;
export type OnAttachmentRemove = (
    attachment: DeserializedMessageAttachment
) => void;

export type BaseMessageFieldProps = {
    onAttachmentClick?: OnAttachmentClick;
    onAttachmentRemove?: OnAttachmentRemove;
} & BaseMessageInputProps;

type Props = {
    submitLabel: string;
    placeholder: string;
    inputRef: React.MutableRefObject<HTMLTextAreaElement | null>;
    useFormSubmitProps: UseFormSubmitReturn<MessageFormData, ThreadMessage>;
    onCancel?: () => void;
    cancelButtonLabel?: string;
    attachments?: DeserializedMessageAttachment[];
    setAttachments?: StateSetter<DeserializedMessageAttachment[]>;
} & BaseMessageFieldProps;

export default function MessageField({
    submitLabel,
    inputRef,
    attachments,
    setAttachments,
    useFormSubmitProps,
    placeholder,
    onCancel,
    cancelButtonLabel,
    onAttachmentClick,
    onAttachmentRemove,
    onFocus,
    disabled,
}: Props) {
    const {
        formState: {errors},
        remoteErrors,
        submitting,
        control,
    } = useFormSubmitProps;
    const theme = useTheme();

    const verticalPadding = '2px';
    const horizontalPadding = '2px';
    const lineHeight = '150%';

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
                    <Box sx={{
                        p: 1,
                    }}>
                        <Controller
                            disabled={submitting || disabled}
                            rules={{required: true}}
                            render={({field: {value, onBlur, onChange, ref}}) => {
                            return <MentionTextarea
                                value={value}
                                onBlur={onBlur}
                                onChange={onChange}
                                inputRef={(r: HTMLTextAreaElement) => {
                                    ref(r);
                                    inputRef.current = r;
                                }}
                                style={{
                                    '&multiLine': {
                                        control: {
                                            backgroundColor: '#fff',
                                            fontSize: theme.typography.body1.fontSize,
                                            fontFamily: theme.typography.body1.fontFamily,
                                            lineHeight,
                                        },
                                        input: {
                                            border: 'none',
                                            outline: 'none',
                                            fontSize: theme.typography.body1.fontSize,
                                            fontFamily: theme.typography.body1.fontFamily,
                                            lineHeight,
                                        },
                                    },
                                    'suggestions': {
                                        list: {
                                            backgroundColor: theme.palette.background.default,
                                            border: `1px solid ${theme.palette.divider}`,
                                            borderRadius: theme.shape.borderRadius,
                                            boxShadow: theme.shadows[1],
                                        },
                                        item: {
                                            padding: theme.spacing(1),
                                            color: theme.palette.primary.main,
                                            backgroundColor: theme.palette.background.default,
                                            '&focused': {
                                                backgroundColor: theme.palette.primary.main,
                                                color: theme.palette.primary.contrastText,
                                            }
                                        }
                                    } as MentionsSuggestionsStyle

                                }}
                                mentionStyle={createUserTagStyle(theme)}
                                placeholder={placeholder}
                                disabled={submitting || disabled}
                                onFocus={onFocus}
                            />
                        }}
                        name={'content'}
                        control={control}
                    />

                    </Box>
                    {attachments ? (
                        <Attachments
                            attachments={attachments}
                            onClick={onAttachmentClick}
                            onDelete={a => {
                                onAttachmentRemove?.(a);

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
                                disabled={submitting || disabled}
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
                                    disabled={submitting || disabled}
                                    onClick={onCancel}
                                >
                                    {cancelButtonLabel!}
                                </Button>
                            ) : null}
                            <LoadingButton
                                type="submit"
                                disabled={submitting || disabled}
                                loading={submitting}
                                color={'primary'}
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
    );
}
