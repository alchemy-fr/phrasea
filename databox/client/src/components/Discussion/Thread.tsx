import React from 'react';
import {ThreadMessage} from '../../types.ts';
import {deleteThreadMessage, getThreadMessages} from '../../api/discussion.ts';
import {ApiCollectionResponse} from '../../api/hydra.ts';
import MessageForm from './MessageForm.tsx';
import {CircularProgress} from '@mui/material';
import DiscussionMessage from './DiscussionMessage.tsx';
import {useChannelRegistration} from '../../lib/pusher.ts';
import {OnActiveAnnotations} from '../Media/Asset/Attribute/Attributes.tsx';
import {AnnotationsControlRef} from '../Media/Asset/Annotations/annotationTypes.ts';
import ConfirmDialog from '../Ui/ConfirmDialog.tsx';
import {toast} from 'react-toastify';
import {useModals} from '@alchemy/navigation';

import {useTranslation} from 'react-i18next';
type Props = {
    threadKey: string;
    threadId?: string;
    onActiveAnnotations: OnActiveAnnotations | undefined;
    annotationsControlRef?: AnnotationsControlRef;
};

export default function Thread({
    threadKey,
    threadId,
    onActiveAnnotations,
    annotationsControlRef,
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
    }, [threadId]);

    useChannelRegistration(
        `thread-${threadKey}`,
        `message`,
        data => {
            appendMessage(data);
        },
    );

    useChannelRegistration(
        `thread-${threadKey}`,
        `message-delete`,
        data => {
            deleteMessage(data.id);
        },
    );

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

                onActiveAnnotations?.([]);

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

    if (threadId && !messages) {
        return <CircularProgress />;
    }

    return (
        <>
            {messages?.result.map(message => (
                <DiscussionMessage
                    key={message.id}
                    message={message}
                    onActiveAnnotations={onActiveAnnotations}
                    onDelete={onDeleteMessage}
                    onEdit={onEditMessage}
                />
            ))}

            <MessageForm
                annotationsControlRef={annotationsControlRef}
                onActiveAnnotations={onActiveAnnotations}
                threadKey={threadKey}
                threadId={threadId}
                onNewMessage={message => {
                    appendMessage(message);
                }}
            />
        </>
    );
}
