import React, {CSSProperties, FocusEventHandler, useRef} from 'react';
import {
    DataFunc,
    Mention,
    MentionsInput,
    MentionsInputProps,
    MentionsInputStyle,
    OnChangeHandlerFunc,
    SuggestionDataItem,
} from 'react-mentions';
import {getUsers} from '../../api/user.ts';
import {isErrorOfCode} from '@alchemy/api';

export type BaseMessageInputProps = {
    disabled?: boolean;
    onFocus?: FocusEventHandler<HTMLTextAreaElement>;
};

type Props = {
    inputRef: React.Ref<HTMLTextAreaElement>;
    style: MentionsInputStyle;
    onChange: OnChangeHandlerFunc;
    mentionStyle: CSSProperties;
    preloadedUsers?: SuggestionDataItem[];
} & Omit<MentionsInputProps, 'onChange' | 'children'> &
    BaseMessageInputProps;

export default function MentionTextarea({
    mentionStyle,
    preloadedUsers,
    ...mentionProps
}: Props) {
    const userApiUnauthorized = useRef(false);
    const userLoader: DataFunc = async (query, callback) => {
        if (!query || userApiUnauthorized.current) {
            callback(preloadedUsers || []);
            return;
        }

        const handledErrorStatuses = [401, 403];
        try {
            const users = await getUsers(
                {
                    query,
                },
                {
                    handledErrorStatuses,
                }
            );
            callback(
                users.map(u => ({
                    id: u.id,
                    display: u.username,
                })) as SuggestionDataItem[]
            );
        } catch (e) {
            if (isErrorOfCode(e, handledErrorStatuses)) {
                userApiUnauthorized.current = true;
            }
            console.error(e);
            callback([]);
            return;
        }
    };

    return (
        <MentionsInput {...mentionProps}>
            <Mention
                trigger="@"
                data={userLoader}
                renderSuggestion={suggestion => {
                    return <div>{suggestion.display}</div>;
                }}
                displayTransform={(_id, display) => `@${display}`}
                appendSpaceOnAdd={true}
                style={mentionStyle}
            />
        </MentionsInput>
    );
}
