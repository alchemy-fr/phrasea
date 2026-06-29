import {AssetPolicy, Workspace} from '../../../types';
import {Chip, ListItemText, TextField} from '@mui/material';
import {CheckboxWidget, FormFieldErrors, FormRow} from '@alchemy/react-form';
import DefinitionManager from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
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

function Item({
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
        />
    );
}
