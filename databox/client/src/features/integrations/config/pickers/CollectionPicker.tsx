'use client';

import type {ComponentProps} from 'react';
import {EntityName} from '@/types/api';
import {iri} from '@/lib/utils/iri';
import {Input} from '@/components/ui/input';
import {CollectionTreePicker} from '@/components/form/CollectionTreePicker';

type Props = {
    /** Without it (instance-wide integration) the ID is typed */
    workspaceId?: string;
    value: string | undefined;
    onChange: (value: string | undefined) => void;
    id?: string;
    disabled?: boolean;
} & Pick<ComponentProps<typeof CollectionTreePicker>, 'className'>;

/**
 * Collection of the workspace, by ID (what `phrasea.uploader.collectionId`
 * expects).
 */
export function CollectionPicker({
    workspaceId,
    value,
    onChange,
    id,
    disabled,
    className,
}: Props) {
    if (!workspaceId) {
        return (
            <Input
                id={id}
                value={value ?? ''}
                onChange={e => onChange(e.target.value || undefined)}
                disabled={disabled}
            />
        );
    }

    return (
        <div id={id} aria-disabled={disabled}>
            <CollectionTreePicker
                workspaceId={workspaceId}
                allowWorkspace={false}
                showSelection
                className={className}
                value={
                    value
                        ? {
                              iri: iri(EntityName.Collection, value),
                              workspaceId,
                              collectionId: value,
                              label: '',
                          }
                        : undefined
                }
                onChange={selection => onChange(selection?.collectionId)}
            />
        </div>
    );
}
