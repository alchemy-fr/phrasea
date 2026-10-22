'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {PlusIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeDefinition, EntityList} from '@/types/api';
import {AssetType, AttributeType, EntityName} from '@/types/api';
import {
    deleteEntityList,
    getAttributeFieldTypes,
    getAttributePolicies,
    getEntityLists,
    postAttributeDefinition,
    postEntityList,
} from '@/lib/api/metadata';
import {iri} from '@/lib/utils/iri';
import {FormDialog} from '@/components/modals/FormDialog';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormRow, Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {useDefinitionsStore} from '@/features/attributes/definitionsStore';
import {EntityListSelect} from '@/components/form/selects';
import type {AttributeConstraints} from './AttributePicker';

const newList = '__new__';

type Props = ModalProps<AttributeDefinition | undefined> & {
    workspaceId: string;
    /** Imposed by the integration: the matching fields are locked */
    constraints?: AttributeConstraints;
    initialName?: string;
};

/**
 * Creates the attribute an integration expects (and the entity list it needs)
 * without leaving the integration form.
 */
export function CreateAttributeDialog({
    open,
    onOpenChange,
    resolve,
    workspaceId,
    constraints = {},
    initialName,
}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const isEntity = constraints.type === AttributeType.Entity;
    const [name, setName] = useState(
        initialName ||
            (isEntity ? t('faces.setup.attribute_default', 'Faces') : '')
    );
    const [type, setType] = useState<string>(
        constraints.type ?? AttributeType.Text
    );
    const [multiple, setMultiple] = useState(!!constraints.multiple);
    const [policy, setPolicy] = useState<string>();
    const [list, setList] = useState<string>();
    const [listName, setListName] = useState(
        t('faces.setup.list_default', 'Persons')
    );

    const policies = useQuery({
        queryKey: ['attribute-policies', workspaceId],
        queryFn: () => getAttributePolicies(workspaceId),
    });
    const fieldTypes = useQuery({
        queryKey: ['attribute-field-types'],
        queryFn: getAttributeFieldTypes,
        staleTime: Infinity,
    });
    // Whether the workspace has any list: none, the list is created along
    const lists = useQuery({
        queryKey: ['entity-lists', workspaceId, 'any'],
        queryFn: () => getEntityLists({workspaceId, limit: 1}),
        enabled: type === AttributeType.Entity,
    });

    // "Public" when the workspace has it, else its first policy
    const policyOptions = (policies.data?.items ?? []).map(p => ({
        value: p['@id'],
        label: p.name,
    }));
    const selectedPolicy =
        policy ??
        policyOptions.find(o => /^public$/i.test(String(o.label)))?.value ??
        policyOptions[0]?.value;
    const typeOptions = (fieldTypes.data ?? []).map(ft => ({
        value: ft.name,
        label: ft.displayName,
    }));
    const newListLabel = t(
        'integration.create_attribute.new_list',
        'New list…'
    );
    const listOptions = useMemo(
        () => [{value: newList, label: newListLabel}],
        [newListLabel]
    );
    // Nothing to pick from: the list is created along
    const selectedList =
        list ?? (lists.data && lists.data.items.length === 0 ? newList : '');
    const needsList = type === AttributeType.Entity;
    const canSubmit =
        !!name.trim() &&
        (!needsList || !!selectedList) &&
        (selectedList !== newList || !!listName.trim());

    const submit = async () => {
        let created: EntityList | undefined;
        if (needsList && selectedList === newList) {
            created = await postEntityList(workspaceId, {
                name: listName.trim(),
            });
        }
        let saved: AttributeDefinition;
        try {
            saved = await postAttributeDefinition({
                workspace: iri(EntityName.Workspace, workspaceId),
                name: name.trim(),
                type: type as AttributeType,
                multiple,
                policy: selectedPolicy || null,
                entityList: needsList
                    ? (created?.['@id'] ?? selectedList)
                    : null,
                searchable: true,
                editable: true,
                editableInGui: true,
                target: AssetType.Asset,
            });
        } catch (e) {
            if (created) {
                // Best effort: the list would be an orphan
                await deleteEntityList(created.id).catch(() => undefined);
            }
            throw e;
        }
        void queryClient.invalidateQueries({
            queryKey: ['entity-lists', workspaceId],
        });
        void queryClient.invalidateQueries({
            queryKey: ['attribute-definitions', 'manage', workspaceId],
        });
        void queryClient.invalidateQueries({
            queryKey: ['attribute-definitions', 'picker', workspaceId],
        });
        useDefinitionsStore.getState().ingest([saved]);
        void queryClient.invalidateQueries({
            queryKey: ['attribute-definitions', 'list'],
        });
        toast.success(
            t('integration.create_attribute.done', 'Attribute created')
        );
        resolve?.(saved);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('integration.create_attribute.title', 'New attribute')}
            submitLabel={t('integration.create_attribute.submit', 'Create')}
            submitIcon={<PlusIcon />}
            canSubmit={canSubmit}
            onSubmit={submit}
        >
            <FormRow
                label={t('integration.create_attribute.name', 'Name')}
                htmlFor="create-attribute-name"
            >
                <Input
                    id="create-attribute-name"
                    autoFocus
                    value={name}
                    onChange={e => setName(e.target.value)}
                />
            </FormRow>
            <FormRow
                label={t('integration.create_attribute.policy', 'Policy')}
                htmlFor="create-attribute-policy"
            >
                <SimpleSelect
                    id="create-attribute-policy"
                    value={selectedPolicy}
                    onValueChange={setPolicy}
                    options={policyOptions}
                />
            </FormRow>
            <FormRow
                label={t('integration.create_attribute.type', 'Type')}
                htmlFor="create-attribute-type"
            >
                <SimpleSelect
                    id="create-attribute-type"
                    value={type}
                    onValueChange={setType}
                    options={typeOptions}
                    disabled={!!constraints.type}
                />
            </FormRow>
            <FormRow>
                <LabeledControl
                    label={t(
                        'integration.create_attribute.multiple',
                        'Multiple values'
                    )}
                >
                    <Checkbox
                        data-testid="create-attribute-multiple"
                        checked={multiple}
                        disabled={!!constraints.multiple}
                        onCheckedChange={v => setMultiple(v === true)}
                    />
                </LabeledControl>
            </FormRow>
            {needsList ? (
                <>
                    <FormRow
                        label={t(
                            'integration.create_attribute.entity_list',
                            'Entity list'
                        )}
                        htmlFor="create-attribute-list"
                    >
                        <EntityListSelect
                            id="create-attribute-list"
                            workspaceId={workspaceId}
                            useIri
                            value={selectedList || undefined}
                            onChange={v => setList(v ?? '')}
                            fixedOptions={listOptions}
                        />
                    </FormRow>
                    {selectedList === newList ? (
                        <FormRow
                            label={t(
                                'integration.create_attribute.list_name',
                                'List name'
                            )}
                            htmlFor="create-attribute-list-name"
                        >
                            <Input
                                id="create-attribute-list-name"
                                value={listName}
                                onChange={e => setListName(e.target.value)}
                            />
                        </FormRow>
                    ) : null}
                </>
            ) : null}
        </FormDialog>
    );
}
