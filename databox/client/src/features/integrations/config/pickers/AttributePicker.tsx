'use client';

import {useCallback} from 'react';
import {useTranslation} from 'react-i18next';
import type {AttributeDefinition} from '@/types/api';
import {getWorkspaceAttributeDefinitions} from '@/lib/api/metadata';
import {AsyncCombobox, ComboOption} from '@/components/form/AsyncCombobox';
import {Input} from '@/components/ui/input';
import {Badge} from '@/components/ui/misc';
import {TagsInput} from '@/components/form/TagsInput';
import {useModals} from '@/components/modals/ModalProvider';
import {CreateAttributeDialog} from './CreateAttributeDialog';

/** What the attribute must be (`widgetOptions` of the schema) */
export type AttributeConstraints = {
    type?: string;
    multiple?: boolean;
    entityList?: boolean;
};

type BaseProps = {
    /** Without it (instance-wide integration) the slug is typed */
    workspaceId?: string;
    constraints?: AttributeConstraints;
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

function matches(d: AttributeDefinition, c: AttributeConstraints): boolean {
    return (
        (!c.type || d.type === c.type) &&
        (!c.multiple || d.multiple) &&
        (!c.entityList || !!d.entityList)
    );
}

function optionOf(d: AttributeDefinition): ComboOption<AttributeDefinition> {
    return {
        value: d.slug,
        label: d.name,
        render: (
            <span className="flex items-center gap-2">
                <span className="truncate">{d.name}</span>
                <Badge variant="muted" className="font-mono">
                    {d.slug}
                </Badge>
            </span>
        ),
        item: d,
    };
}

/**
 * Attribute of the workspace, by slug, with "Create…" for the missing one:
 * the dialog is constrained by what the integration expects.
 */
export function AttributePicker(props: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {workspaceId, constraints = {}, id, placeholder, disabled} = props;
    const {type, multiple: multi, entityList} = constraints;

    const load = useCallback(
        async (query: string) => {
            const page = await getWorkspaceAttributeDefinitions({
                workspaceId: workspaceId!,
                query: query || undefined,
                type,
            });

            return page.items
                .filter(d => matches(d, {type, multiple: multi, entityList}))
                .map(optionOf);
        },
        [workspaceId, type, multi, entityList]
    );
    const resolve = useCallback(
        async (slug: string) =>
            (await load('')).find(o => o.value === slug) ?? {
                value: slug,
                label: slug,
            },
        [load]
    );
    const create = useCallback(
        async (name: string) => {
            const created = await openModal(CreateAttributeDialog, {
                workspaceId: workspaceId!,
                constraints,
                initialName: name || undefined,
            });

            return created ? optionOf(created) : undefined;
        },
        [openModal, workspaceId, constraints]
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
        queryKey: [
            'attribute-definitions',
            'picker',
            workspaceId,
            type ?? null,
            !!multi,
            !!entityList,
        ],
        loadOptions: load,
        resolveValue: resolve,
        onCreate: create,
        createWithoutQuery: true,
        createLabel: (q: string) =>
            q
                ? t(
                      'integration.attribute_picker.create',
                      'Create “{{name}}”…',
                      {name: q}
                  )
                : t(
                      'integration.attribute_picker.create_new',
                      'Create an attribute…'
                  ),
        placeholder:
            placeholder ??
            t(
                'integration.attribute_picker.placeholder',
                'Select an attribute…'
            ),
    };

    return props.multiple ? (
        <AsyncCombobox<AttributeDefinition>
            {...shared}
            multiple
            value={props.value}
            onChange={props.onChange}
        />
    ) : (
        <AsyncCombobox<AttributeDefinition>
            {...shared}
            value={props.value}
            onChange={props.onChange}
        />
    );
}
