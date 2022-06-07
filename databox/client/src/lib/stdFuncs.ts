import {MouseEvent} from "react";

export function stopPropagation(e: MouseEvent): void {
    e.stopPropagation();
}
