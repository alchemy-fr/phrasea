import {useSortable} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {PropsWithChildren} from "react";

type Props = PropsWithChildren<{
    id: string;
}>;

export default function SortableNode({
                                         id,
                                         children,
                                     }: Props) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id});

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        touchAction: 'manipulation',
        opacity: isDragging ? 0.5 : 1,
    };

    return <div
        ref={setNodeRef}
        style={style}
        {...attributes}
        {...listeners}
    >
        {children}
    </div>
}
