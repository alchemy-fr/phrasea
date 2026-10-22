'use client';

import {useTranslation} from 'react-i18next';
import {
    ImageOffIcon,
    ImagesIcon,
    ImageUpIcon,
    MergeIcon,
    PencilIcon,
    ThumbsDownIcon,
    ThumbsUpIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';
import type {AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    ContextMenuItem,
    ContextMenuLabel,
    ContextMenuSeparator,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    MenuEmpty,
} from '@/components/ui/menu';
import {isDraft} from './drafts';

export type EntityMenuActions = {
    onEdit: (e: AttributeEntity) => void;
    onSetStatus: (
        entities: AttributeEntity[],
        status: AttributeEntityStatus
    ) => void;
    onPreview: (e: AttributeEntity) => void;
    /** Opens the file picker of a new photo */
    onPickImage: (e: AttributeEntity) => void;
    onRemoveImage: (e: AttributeEntity) => void;
    onMerge: (entities: AttributeEntity[]) => void;
    onDelete: (entities: AttributeEntity[]) => void;
    onDiscardDraft?: (e: AttributeEntity) => void;
};

/**
 * Shared list of actions on one or several values of an entity list, rendered
 * either in a context menu or in a dropdown menu. Several values get a header
 * giving their count, and the bulk actions only.
 */
export function EntityMenuItems({
    entities,
    list,
    variant,
    actions,
}: {
    entities: AttributeEntity[];
    list: EntityList;
    variant: 'context' | 'dropdown';
    actions: EntityMenuActions;
}) {
    const {t} = useTranslation();
    const Item = variant === 'context' ? ContextMenuItem : DropdownMenuItem;
    const Label = variant === 'context' ? ContextMenuLabel : DropdownMenuLabel;
    const Sep =
        variant === 'context' ? ContextMenuSeparator : DropdownMenuSeparator;

    if (entities.length === 0) {
        return <MenuEmpty />;
    }

    if (entities.length > 1) {
        return (
            <>
                <Label data-testid="entity-menu-count">
                    {t('entity.menu.count', '{{count}} values selected', {
                        count: entities.length,
                    })}
                </Label>
                <Sep />
                <Item
                    data-testid="entity-menu-approve"
                    onSelect={() =>
                        actions.onSetStatus(
                            entities,
                            AttributeEntityStatus.Approved
                        )
                    }
                >
                    <ThumbsUpIcon /> {t('entity.approve', 'Approve')}
                </Item>
                <Item
                    data-testid="entity-menu-reject"
                    onSelect={() =>
                        actions.onSetStatus(
                            entities,
                            AttributeEntityStatus.Rejected
                        )
                    }
                >
                    <ThumbsDownIcon /> {t('entity.reject', 'Reject')}
                </Item>
                <Item
                    data-testid="entity-menu-merge"
                    onSelect={() => actions.onMerge(entities)}
                >
                    <MergeIcon /> {t('entity.merge', 'Merge')}
                </Item>
                <Sep />
                <Item
                    variant="destructive"
                    data-testid="entity-menu-delete"
                    onSelect={() => actions.onDelete(entities)}
                >
                    <Trash2Icon /> {t('common.delete', 'Delete')}
                </Item>
            </>
        );
    }

    const e = entities[0];
    if (isDraft(e)) {
        return actions.onDiscardDraft ? (
            <Item
                data-testid="entity-menu-discard-draft"
                onSelect={() => actions.onDiscardDraft?.(e)}
            >
                <XIcon /> {t('entity.discard_draft', 'Discard the draft')}
            </Item>
        ) : (
            <MenuEmpty />
        );
    }

    return (
        <>
            <Item
                data-testid="entity-menu-edit"
                onSelect={() => actions.onEdit(e)}
            >
                <PencilIcon /> {t('entity.edit_form', 'Edit…')}
            </Item>
            {e.status !== AttributeEntityStatus.Approved ? (
                <Item
                    data-testid="entity-menu-approve"
                    onSelect={() =>
                        actions.onSetStatus([e], AttributeEntityStatus.Approved)
                    }
                >
                    <ThumbsUpIcon /> {t('entity.approve', 'Approve')}
                </Item>
            ) : null}
            {e.status !== AttributeEntityStatus.Rejected ? (
                <Item
                    data-testid="entity-menu-reject"
                    onSelect={() =>
                        actions.onSetStatus([e], AttributeEntityStatus.Rejected)
                    }
                >
                    <ThumbsDownIcon /> {t('entity.reject', 'Reject')}
                </Item>
            ) : null}
            <Item
                data-testid="entity-menu-preview"
                onSelect={() => actions.onPreview(e)}
            >
                <ImagesIcon /> {t('entity.preview', 'Assets using this value')}
            </Item>
            {list.withImages ? (
                <>
                    <Item
                        data-testid="entity-menu-image-change"
                        onSelect={() => actions.onPickImage(e)}
                    >
                        <ImageUpIcon />{' '}
                        {e.thumbnail
                            ? t('entity.image.change', 'Change the photo…')
                            : t('entity.image.add', 'Add a photo…')}
                    </Item>
                    {e.thumbnail ? (
                        <Item
                            data-testid="entity-menu-image-remove"
                            onSelect={() => actions.onRemoveImage(e)}
                        >
                            <ImageOffIcon />{' '}
                            {t('entity.image_remove', 'Remove the photo')}
                        </Item>
                    ) : null}
                </>
            ) : null}
            <Sep />
            <Item
                variant="destructive"
                data-testid="entity-menu-delete"
                onSelect={() => actions.onDelete([e])}
            >
                <Trash2Icon /> {t('common.delete', 'Delete')}
            </Item>
        </>
    );
}
