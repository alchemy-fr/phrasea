import React from "react";
import {ThreadMessage} from "../../types.ts";
import {getThreadMessages} from "../../api/discussion.ts";
import {ApiCollectionResponse} from "../../api/hydra.ts";
import MessageForm from "./MessageForm.tsx";
import {CircularProgress} from "@mui/material";
import DiscussionMessage from "./DiscussionMessage.tsx";
import {useChannelRegistration} from "../../lib/pusher.ts";
import {OnActiveAnnotations} from "../Media/Asset/Attribute/Attributes.tsx";

type Props = {
    threadKey: string;
    threadId?: string;
    onActiveAnnotations: OnActiveAnnotations | undefined;
};

export default function Thread({
    threadKey,
    threadId,
    onActiveAnnotations,
}: Props) {
    const [messages, setMessages] = React.useState<ApiCollectionResponse<ThreadMessage>>();

    const appendMessage = React.useCallback((message: ThreadMessage) => {
        message.acknowledged = true;

        setMessages(p => p ? {
            ...p,
            result: p.result.some(m => m.id === message.id) ?
                p.result.map(m => m.id === message.id ? message : m)
                : p.result.concat(message),
            total: p.total + 1,
        } : {
            result: [message],
            total: 1,
        });
    }, [setMessages]);

    React.useEffect(() => {
        if (threadId) {
            getThreadMessages(threadId).then((res) => {
                setMessages(res);
            });
        }
    }, [threadId]);

    useChannelRegistration(
        `thread-${threadId}`,
        `message`,
        (data) => {
            appendMessage(data);
        }, !!threadId
    );

    if (threadId && !messages) {
        return <CircularProgress/>;
    }

    return <>
        {messages?.result.map((message) => (
            <DiscussionMessage
                key={message.id}
                message={message}
                onActiveAnnotations={onActiveAnnotations}
            />
        ))}

        <MessageForm
            threadKey={threadKey}
            threadId={threadId}
            onNewMessage={(message) => {
                appendMessage(message);
            }}
        />
    </>
}
