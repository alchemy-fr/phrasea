import nl2br from "react-nl2br";
import React, {PropsWithChildren} from "react";
import reactStringReplace from "react-string-replace";
import {FreeNode, replaceText} from "../../lib/reactText.tsx";
import {styled} from "@mui/material/styles";
import {alpha, Theme} from "@mui/material";

export function formatMessage(
    value?: string,
): FreeNode {
    if (!value) {
        return [];
    }

    const replaced = reactStringReplace(
        value,
        /@\[(.+?)]\((?:.+?)\)/g,
        (m, index) => {
            return <UserTag key={index}>@{m}</UserTag>;
        }
    );

    const linkReplaced = reactStringReplace(
        replaced,
        /(https?:\/\/\S+)/g,
        (m, index) => {
            const truncated = truncateUrl(m, 50);

            return <a key={index}
                        href={m}
                        title={m}
                        target="_blank"
                        rel="noreferrer">{truncated}</a>;
        }
    );

    return replaceText(linkReplaced, nl2br);
}

export const createUserTagStyle = (theme: Theme) => ({
    backgroundColor: alpha(theme.palette.primary.main, 0.1),
    padding: '1px 3px',
    margin: '-1px -3px',
    borderRadius: 3,
});

const UserTag = styled('span')(({theme}) => createUserTagStyle(theme));

function truncateUrl(url: string, maxLength: number): string {
    if (url.length <= maxLength) return url;

    const keepLength = Math.floor(maxLength / 2) - 2; // Keeping both start and end parts
    const start = url.slice(0, keepLength);
    const end = url.slice(-keepLength);

    return `${start}…${end}`;
}
