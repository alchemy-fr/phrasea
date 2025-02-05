import React, {CSSProperties, FocusEventHandler} from 'react';
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
    const userLoader: DataFunc = async (query, callback) => {
        if (!query) {
            callback(preloadedUsers || []);
            return;
        }

        try {
            const users = await getUsers({
                query,
            });
            callback(
                users.map(u => ({
                    id: u.id,
                    display: u.username,
                })) as SuggestionDataItem[]
            );
        } catch (e) {
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
