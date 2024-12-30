import React from "react";
import {ThreadMessage} from "../../types.ts";
import {getThreadMessages} from "../../api/discussion.ts";
import {ApiCollectionResponse} from "../../api/hydra.ts";
import MessageForm from "./MessageForm.tsx";
import {CircularProgress} from "@mui/material";
import DiscussionMessage from "./DiscussionMessage.tsx";

type Props = {
    threadKey: string;
    threadId?: string;
};

export default function Thread({
    threadKey,
    threadId,
}: Props) {
    const [messages, setMessages] = React.useState<ApiCollectionResponse<ThreadMessage>>();

    React.useEffect(() => {
        if (threadId) {
            getThreadMessages(threadId).then((res) => {
                setMessages(res);
            });
        }
    }, [threadId]);

    if (threadId && !messages) {
        return <CircularProgress/>;
    }

    return <>
        {messages?.result.map((message) => (
            <DiscussionMessage
                key={message.id}
                message={message}
            />
        ))}

        <MessageForm
            threadKey={threadKey}
            threadId={threadId}
            onNewMessage={(message) => {
                setMessages(p => p ? {
                    ...p,
                    result: p.result.concat(message),
                    total: p.total + 1,
                } : {
                    result: [message],
                    total: 1,
                });
            }}
        />
    </>
}
