import {AssetAnnotation, ThreadMessage} from "../../types.ts";
import {Divider} from "@mui/material";
import moment from "moment";
import {OnActiveAnnotations} from "../Media/Asset/Attribute/Attributes.tsx";

type Props = {
    message: ThreadMessage;
    onActiveAnnotations?: OnActiveAnnotations | undefined;
};

export default function DiscussionMessage({
    message,
    onActiveAnnotations,
}: Props) {
    const m = moment(message.createdAt);
    const annotations: AssetAnnotation[] = message.attachments?.filter(a => a.type === 'annotation').map(a => JSON.parse(a.content) as AssetAnnotation) ?? [];

    return <>
        <div
            onMouseEnter={onActiveAnnotations && annotations.length > 0 ? () => onActiveAnnotations!(annotations) : undefined}
        >
            <div>
                <small>
                    <strong>
                        {message.author.username}
                    </strong>
                    {' - '}
                    <span title={m.format('LLL')}>
                {m.calendar()}
                    </span>
                </small>
            </div>
            <p>{message.content}</p>
        </div>
        <Divider sx={{
            mb: 1,
        }}/>
    </>
}
