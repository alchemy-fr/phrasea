import {FieldValues} from "react-hook-form";
import {useSortable} from "@dnd-kit/sortable";
import {CSS} from "@dnd-kit/utilities";
import {CollectionItem, CollectionItemProps} from "./CollectionItem";

export default function SortableCollectionItem<TFieldValues extends FieldValues>({
    id,
    ...props
}: { id: string } & CollectionItemProps<TFieldValues>) {
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

    return (
        <div ref={setNodeRef} style={style} {...attributes}>
            <CollectionItem
                {...props}
                dragListeners={listeners}
                sortable={true}
            />
        </div>
    );
}
