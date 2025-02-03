import React, {ChangeEventHandler, CSSProperties, FocusEventHandler} from 'react';
import {Mention, MentionsInput, MentionsInputProps, MentionsInputStyle} from 'react-mentions'

export type BaseMessageInputProps = {
    disabled?: boolean;
    onFocus?: FocusEventHandler<HTMLTextAreaElement>;
};

type Props = {
    value: string;
    inputRef: React.Ref<HTMLTextAreaElement>;
    style: MentionsInputStyle;
    onChange: ChangeEventHandler<HTMLTextAreaElement>;
    mentionStyle: CSSProperties;
} & Omit<MentionsInputProps, 'onChange' | 'children'> & BaseMessageInputProps;

export default function MentionTextarea({
    inputRef,
    value,
    onChange,
    style,
    mentionStyle,
    ...textareaProps
}: Props) {
    return (
        <MentionsInput
            {...textareaProps}
            value={value}
            onChange={onChange}
            inputRef={inputRef}
            required={true}
            style={style}
        >
            <Mention
                trigger="@"
                data={[
                    {id: 1, display: 'John Doe'},
                    {id: 2, display: 'Jane Doe'},
                ]}
                renderSuggestion={(suggestion) => {
                    return <div>{suggestion.display}</div>
                }}
                displayTransform={(_id, display) => `@${display}`}
                appendSpaceOnAdd={true}
                style={mentionStyle}
            />
        </MentionsInput>
    );
}
