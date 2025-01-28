import React from 'react';
import {ThreadMessage} from '../../types.ts';
import {deleteThreadMessage, getThreadMessages} from '../../api/discussion.ts';
import {ApiCollectionResponse} from '../../api/hydra.ts';
import MessageForm, {BaseMessageFormProps} from './MessageForm.tsx';
import {Box, Skeleton} from '@mui/material';
import DiscussionMessage from './DiscussionMessage.tsx';
import {useChannelRegistration} from '../../lib/pusher.ts';
import ConfirmDialog from '../Ui/ConfirmDialog.tsx';
import {toast} from 'react-toastify';
import {useModals} from '@alchemy/navigation';

import {useTranslation} from 'react-i18next';

export type BaseThreadProps = {
    onMessageDelete?: (message: ThreadMessage) => void;
} & BaseMessageFormProps;

type Props = {
    threadKey: string;
    threadId?: string;
} & BaseThreadProps;

export default function Thread({
    threadKey,
    threadId,
    messageFormRef,
    onMessageDelete,
    ...formProps
}: Props) {
    const [messages, setMessages] =
        React.useState<ApiCollectionResponse<ThreadMessage>>();
    const {openModal} = useModals();
    const {t} = useTranslation();

    const appendMessage = React.useCallback(
        (message: ThreadMessage) => {
            message.acknowledged = true;

            setMessages(p =>
                p
                    ? {
                          ...p,
                          result: p.result.some(m => m.id === message.id)
                              ? p.result.map(m =>
                                    m.id === message.id ? message : m
                                )
                              : p.result.concat(message),
                          total: p.total + 1,
                      }
                    : {
                          result: [message],
                          total: 1,
                      }
            );
        },
        [setMessages]
    );

    const deleteMessage = React.useCallback(
        (id: string) => {
            setMessages(p =>
                p
                    ? {
                          ...p,
                          result: p.result.filter(m => m.id !== id),
                          total: p.total - 1,
                      }
                    : undefined
            );
        },
        [setMessages]
    );

    React.useEffect(() => {
        setMessages(undefined);
        if (threadId) {
            getThreadMessages(threadId).then(res => {
                setMessages(res);
            });
        }
    }, [threadId, threadKey]);

    useChannelRegistration(`thread-${threadKey}`, `message`, data => {
        appendMessage(data);
    });

    useChannelRegistration(`thread-${threadKey}`, `message-delete`, data => {
        deleteMessage(data.id);
    });

    const onDeleteMessage = (message: ThreadMessage): void => {
        openModal(ConfirmDialog, {
            title: t(
                'thread.message.delete.confirm.title',
                'Are you sure you want to delete this message?'
            ),
            onConfirm: async () => {
                await deleteThreadMessage(message.id);

                setMessages(p =>
                    p
                        ? {
                              ...p,
                              result: p.result.filter(m => m.id !== message.id),
                              total: p.total - 1,
                          }
                        : undefined
                );

                onMessageDelete?.(message);

                toast.success(
                    t(
                        'thread.message.delete.confirm.success',
                        'Message has been removed!'
                    ) as string
                );
            },
        });
    };

    const onEditMessage = (message: ThreadMessage): void => {
        setMessages(p =>
            p
                ? {
                      ...p,
                      result: p.result.map(m =>
                          m.id === message.id ? message : m
                      ),
                  }
                : undefined
        );
    };

    const loadingMessages = Boolean(threadId && !messages);

    return (
        <>
            {loadingMessages ? (
                <Box
                    sx={{
                        mb: 2,
                    }}
                >
                    <Skeleton />
                    <Skeleton />
                </Box>
            ) : (
                messages?.result.map(message => (
                    <DiscussionMessage
                        key={message.id}
                        message={message}
                        onAttachmentClick={formProps.onAttachmentClick}
                        onDelete={onDeleteMessage}
                        onEdit={onEditMessage}
                    />
                ))
            )}

            <MessageForm
                {...formProps}
                disabled={loadingMessages}
                ref={messageFormRef}
                threadKey={threadKey}
                threadId={threadId}
                onNewMessage={message => {
                    appendMessage(message);
                }}
            />
        </>
    );
}
