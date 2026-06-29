import {
    AssetPolicy,
    AssetPolicyCondition,
    Group,
    User,
    Workspace,
} from '../../../types';
import {Chip, Hidden, ListItemText, TextField} from '@mui/material';
import {
    CheckboxWidget,
    FormFieldErrors,
    FormRow,
    SortableCollectionWidget,
} from '@alchemy/react-form';
import DefinitionManager from './DefinitionManager/DefinitionManager.tsx';
import {Trans, useTranslation} from 'react-i18next';
import {
    deleteAssetPolicy,
    getAssetPolicies,
    postAssetPolicy,
    putAssetPolicy,
} from '../../../api/assetPolicy';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import {
    DefinitionItemFormProps,
    DefinitionItemProps,
} from './DefinitionManager/managerTypes.ts';
import {EntityName} from '../../../api/types.ts';
import {createIriFromId} from '@alchemy/api';
import UserSelect from '../../Form/UserSelect.tsx';
import GroupSelect from '../../Form/GroupSelect.tsx';
import IconFormLabel from '../../Form/IconFormLabel.tsx';
import React from 'react';
import AssetPolicyActionWidget from '../../Form/AssetPolicy/AssetPolicyActionWidget.tsx';
import PlayCircleFilledWhiteIcon from '@mui/icons-material/PlayCircleFilledWhite';
import FilterAltIcon from '@mui/icons-material/FilterAlt';
import AssetPolicyConditionWidget from '../../Form/AssetPolicy/AssetPolicyConditionWidget.tsx';

function Item({
    workspace,
    usedFormSubmit: {
        submitting,
        register,
        control,
        formState: {errors},
    },
}: DefinitionItemFormProps<AssetPolicy>) {
    const {t} = useTranslation();

    return (
        <>
            <FormRow>
                <TextField
                    label={t('form.asset_policy.name.label', 'Name')}
                    {...register('name')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t('form.asset_policy.enabled.label', 'Enabled')}
                    control={control}
                    name={'enabled'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'enabled'} errors={errors} />
            </FormRow>
            <FormRow>
                <UserSelect
                    label={t('form.asset_policy.users.label', 'Users')}
                    control={control}
                    name={'users'}
                    isMulti={true}
                />
            </FormRow>
            <FormRow>
                <GroupSelect
                    label={t('form.asset_policy.groups.label', 'Groups')}
                    control={control}
                    name={'groups'}
                    isMulti={true}
                />
            </FormRow>
            <FormRow>
                <SortableCollectionWidget
                    errors={errors}
                    emptyItem={{
                        field: '',
                        operator: '=',
                        value: null,
                    }}
                    control={control}
                    label={
                        <IconFormLabel startIcon={<FilterAltIcon />}>
                            {t(
                                'form.asset_policy.conditions.label',
                                'Conditions'
                            )}
                        </IconFormLabel>
                    }
                    path={'conditions'}
                    register={register}
                    addLabel={t(
                        'form.asset_policy.conditions.add',
                        'Add Condition'
                    )}
                    removeLabel={
                        <Trans
                            t={t}
                            i18nKey="form.asset_policy.conditions.remove"
                        >
                            Remove <Hidden smDown>this Condition</Hidden>
                        </Trans>
                    }
                    renderForm={({index, path}) => {
                        return (
                            <FormRow>
                                <AssetPolicyConditionWidget
                                    path={`${path}.${index}` as any}
                                    control={control}
                                    workspaceId={workspace.id}
                                    register={register}
                                />
                            </FormRow>
                        );
                    }}
                />
            </FormRow>
            <FormRow>
                <SortableCollectionWidget
                    errors={errors}
                    emptyItem={{
                        name: null,
                    }}
                    control={control}
                    label={
                        <IconFormLabel
                            startIcon={<PlayCircleFilledWhiteIcon />}
                        >
                            {t('form.asset_policy.actions.label', 'Actions')}
                        </IconFormLabel>
                    }
                    path={'actions'}
                    register={register}
                    addLabel={t('form.asset_policy.actions.add', 'Add Action')}
                    removeLabel={
                        <Trans t={t} i18nKey="form.asset_policy.actions.remove">
                            Remove <Hidden smDown>this Action</Hidden>
                        </Trans>
                    }
                    renderForm={({index, path}) => {
                        return (
                            <FormRow>
                                <AssetPolicyActionWidget
                                    path={`${path}.${index}` as any}
                                    control={control}
                                    workspaceId={workspace.id}
                                    register={register}
                                />
                            </FormRow>
                        );
                    }}
                />
            </FormRow>
        </>
    );
}

function ListItem({data}: DefinitionItemProps<AssetPolicy>) {
    const {t} = useTranslation();

    return (
        <ListItemText
            primary={data.name}
            slotProps={{
                secondary: {
                    component: 'div',
                },
            }}
            secondary={
                <>
                    {!data.enabled && (
                        <Chip
                            color={'error'}
                            label={t('chip.disabled', 'Disabled')}
                            size={'small'}
                        />
                    )}
                </>
            }
        />
    );
}

function createNewItem(): Partial<AssetPolicy> {
    return {
        name: '',
        enabled: true,
        users: [],
        groups: [],
        conditions: [],
        actions: [],
    };
}

type Props = DataTabProps<Workspace>;

export default function AssetPolicyManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: AssetPolicy) => {
        if (data.id) {
            const postData = {...data} as Partial<AssetPolicy>;
            delete postData.workspace;

            return await putAssetPolicy(data.id, postData);
        } else {
            return await postAssetPolicy({
                ...data,
                workspace: createIriFromId(EntityName.Workspace, workspace.id),
            });
        }
    };

    return (
        <DefinitionManager
            itemComponent={Item}
            listComponent={ListItem}
            load={({nextUrl}) =>
                getAssetPolicies({
                    workspaceId: workspace.id,
                    nextUrl,
                })
            }
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('asset_policy.new.label', 'New Asset Policy')}
            handleSave={handleSave}
            handleDelete={deleteAssetPolicy}
            normalizeData={normalizeData}
            denormalizeData={denormalizeData}
        />
    );
}

function denormalizeData(data: AssetPolicy): AssetPolicy {
    return {
        ...data,
        users: data.users.map(denormalizeUser),
        groups: data.groups.map(denormalizeUser),
        conditions: data.conditions.map(denormalizeCondition),
    };
}

function denormalizeUser<T extends User | Group>(user: T | string): string {
    if (typeof user === 'string') {
        return user;
    }

    return user.id;
}

function denormalizeCondition(
    condition: AssetPolicyCondition
): AssetPolicyCondition {
    return condition; // TODO
}

function normalizeUser<T extends User | Group>(user: T | string): string {
    if (typeof user === 'string') {
        return user;
    }

    return user.id;
}

function normalizeData(data: AssetPolicy): AssetPolicy {
    return {
        ...data,
        users: data.users?.map(normalizeUser) as AssetPolicy['users'],
        groups: data.groups?.map(normalizeUser) as AssetPolicy['groups'],
    };
}
