'use client';

import {useState} from 'react';
import {
    CollectionTreePicker,
    TreeSelection,
} from '@/components/form/CollectionTreePicker';
import {EntityName} from '@/types/api';
import {iri} from '@/lib/utils/iri';
import {FieldError} from '@/components/ui/input';

/**
 * Destination collection of an action, stored as an ID.
 */
export function CollectionPickerField({
    value,
    onChange,
    error,
}: {
    value: string | undefined;
    onChange: (collectionId: string | undefined) => void;
    error?: string;
}) {
    // The picker reveals the collection selected when it mounts
    const [selection, setSelection] = useState<TreeSelection | undefined>(
        value
            ? {
                  iri: iri(EntityName.Collection, value),
                  workspaceId: '',
                  collectionId: value,
                  label: '',
              }
            : undefined
    );

    return (
        <div>
            <CollectionTreePicker
                value={selection}
                onChange={s => {
                    setSelection(s);
                    onChange(s?.collectionId);
                }}
                requireCapability="createAsset"
                allowWorkspace={false}
                showSelection
            />
            <FieldError>{error}</FieldError>
        </div>
    );
}
