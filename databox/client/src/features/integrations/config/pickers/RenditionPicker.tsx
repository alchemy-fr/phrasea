'use client';

import {useCallback} from 'react';
import {useTranslation} from 'react-i18next';
import type {RenditionDefinition} from '@/types/api';
import {getRenditionDefinitions} from '@/lib/api/misc';
import {AsyncCombobox, ComboOption} from '@/components/form/AsyncCombobox';
import {Input} from '@/components/ui/input';
import {TagsInput} from '@/components/form/TagsInput';

type BaseProps = {
    /** Without it (instance-wide integration) the name is typed */
    workspaceId?: string;
    id?: string;
    placeholder?: string;
    disabled?: boolean;
};

type Props = BaseProps &
    (
        | {
              multiple?: false;
              value: string | undefined;
              onChange: (value: string | undefined) => void;
          }
        | {
              multiple: true;
              value: string[];
              onChange: (value: string[]) => void;
          }
    );

function optionOf(d: RenditionDefinition): ComboOption<RenditionDefinition> {
    return {value: d.name, label: d.name, item: d};
}

/**
 * Rendition definition of the workspace, by name (what the configuration
 * holds, see `RenditionConfigNormalizer`).
 */
export function RenditionPicker(props: Props) {
    const {t} = useTranslation();
    const {workspaceId, id, placeholder, disabled} = props;

    const load = useCallback(
        async (query: string) =>
            (
                await getRenditionDefinitions({
                    workspaceId: workspaceId!,
                    query: query || undefined,
                })
            ).items.map(optionOf),
        [workspaceId]
    );
    const resolve = useCallback(
        async (name: string) => ({value: name, label: name}),
        []
    );

    if (!workspaceId) {
        return props.multiple ? (
            <TagsInput
                id={id}
                value={props.value}
                onChange={props.onChange}
                placeholder={placeholder}
                disabled={disabled}
            />
        ) : (
            <Input
                id={id}
                value={props.value ?? ''}
                onChange={e => props.onChange(e.target.value || undefined)}
                placeholder={placeholder}
                disabled={disabled}
            />
        );
    }

    const shared = {
        id,
        disabled,
        queryKey: ['rendition-definitions', 'picker', workspaceId],
        loadOptions: load,
        resolveValue: resolve,
        placeholder:
            placeholder ??
            t(
                'integration.rendition_picker.placeholder',
                'Select a rendition…'
            ),
    };

    return props.multiple ? (
        <AsyncCombobox<RenditionDefinition>
            {...shared}
            multiple
            value={props.value}
            onChange={props.onChange}
        />
    ) : (
        <AsyncCombobox<RenditionDefinition>
            {...shared}
            value={props.value}
            onChange={props.onChange}
        />
    );
}
