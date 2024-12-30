import {ThreadMessage} from "../../types.ts";
import {Divider} from "@mui/material";
import moment from "moment";

type Props = {
    message: ThreadMessage;
};

export default function DiscussionMessage({
    message,
}: Props) {

    const m = moment(message.createdAt);

    return <>
        <div>
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
