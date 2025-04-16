import {ReactNode} from "react";
import reactStringReplace from "react-string-replace";

export function replaceEntities(query: string): ReactNode
{
    return reactStringReplace(query, /(@<[^:]+:[^>]+>)/g, (match) => {
        const m = match.match(/@<([^:]+):([^>]+)>/);
        return <span key={m![1]} className={'entity'} title={m![1]}>{m![2]}</span>;
    });
}

export function writeEntity(id: string, label: string): string {
    return `@<${id}:${label}>`;
}
