import GroupSelect from '../../Form/GroupSelect';
import UserSelect from '../../Form/UserSelect';
import {
    Button,
    FormGroup,
    FormHelperText,
    FormLabel,
    Paper,
} from '@mui/material';
import {Grid2 as Grid} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {useForm} from 'react-hook-form';
import {FormRow} from '@alchemy/react-form';
import {
    deleteAttributeFilterRule,
    saveAttributeFilterRule,
} from '../../../api/attribute-filter-rule';
import {FormFieldErrors} from '@alchemy/react-form';
import {AttributeFilterRule} from '../../../types';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import EditIcon from '@mui/icons-material/Edit';
import {useModals} from '@alchemy/navigation';
import SearchConditionDialog from '../Search/AQL/SearchConditionDialog';

type FilterRuleFormData = {
    id?: string | undefined;
    userIds: string[];
    groupIds: string[];
    condition: string;
};

type Props = {
    data?: AttributeFilterRule | undefined;
    onCancel: () => void;
    onDelete: (id?: string) => void;
    onSubmit: (data: FilterRuleFormData) => void;
    workspaceId: string;
};

export default function FilterRuleForm({
    data,
    onSubmit,
    onDelete,
    onCancel,
    workspaceId,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    const {
        handleSubmit,
        control,
        setValue,
        watch,
        formState: {errors, isDirty},
    } = useForm<any>({
        defaultValues: data
            ? {
                  id: data.id,
                  userIds: data.users.map(u => u.id),
                  groupIds: data.groups.map(g => g.id),
                  condition: data.condition,
              }
            : {
                  userIds: [],
                  groupIds: [],
                  condition: '',
              },
    });
    useDirtyFormPrompt(isDirty);

    const condition: string = watch('condition') ?? '';

    const editCondition = () => {
        openModal(SearchConditionDialog, {
            condition: {
                id: data?.id ?? 'new-rule',
                query: condition,
            },
            onUpsert: c => {
                setValue('condition', c.query, {shouldDirty: true});
            },
            workspaceId,
        });
    };

    const saveRule = async (formData: FilterRuleFormData) => {
        await saveAttributeFilterRule({
            id: formData.id,
            userIds: formData.userIds ?? [],
            groupIds: formData.groupIds ?? [],
            condition: formData.condition,
            workspaceId,
        });

        onSubmit(formData);
    };

    const deleteClick = async () => {
        if (
            !window.confirm(
                t(
                    'filter_rule.confirm_delete_this_rule',
                    `Confirm delete this rule?`
                )
            )
        ) {
            return;
        }

        const id = data!.id!;
        await deleteAttributeFilterRule(id);
        onDelete(id);
    };

    return (
        <form onSubmit={handleSubmit(saveRule)}>
            <Paper
                elevation={2}
                sx={{
                    p: 2,
                }}
            >
                <div className="col-md-12">
                    {t('filter_rule.rule_applies_for', `Rule applies for:`)}
                </div>
                <Grid container spacing={2}>
                    <Grid size={6}>
                        <FormRow>
                            <FormLabel>
                                {t('filter_rule.users', 'Users')}
                            </FormLabel>
                            <UserSelect
                                name={'userIds'}
                                control={control}
                                isMulti={true}
                            />
                            <FormFieldErrors
                                field={'userIds'}
                                errors={errors}
                            />
                        </FormRow>
                    </Grid>
                    <Grid size={6}>
                        <FormRow>
                            <FormLabel>
                                {t('filter_rule.groups', 'Groups')}
                            </FormLabel>
                            <GroupSelect
                                name={'groupIds'}
                                control={control}
                                isMulti={true}
                            />
                            <FormFieldErrors
                                field={'groupIds'}
                                errors={errors}
                            />
                        </FormRow>
                    </Grid>
                    <Grid size={12}>
                        <FormHelperText>
                            {t(
                                'filter_rule.targets_helper',
                                `Leave both empty to apply this rule to every user.`
                            )}
                        </FormHelperText>
                    </Grid>
                    <Grid size={12}>
                        <FormRow>
                            <FormGroup>
                                <FormLabel>
                                    {t(
                                        'filter_rule.condition.label',
                                        'Condition'
                                    )}
                                </FormLabel>
                                <div>
                                    {condition ? (
                                        <code>{condition}</code>
                                    ) : (
                                        <em>
                                            {t(
                                                'filter_rule.condition.empty',
                                                'No condition defined yet'
                                            )}
                                        </em>
                                    )}
                                </div>
                                <div>
                                    <Button
                                        startIcon={<EditIcon />}
                                        onClick={editCondition}
                                    >
                                        {t(
                                            'filter_rule.condition.edit',
                                            'Edit condition'
                                        )}
                                    </Button>
                                </div>
                                <FormHelperText>
                                    {t(
                                        'filter_rule.condition.helper',
                                        `Assets in this workspace will only be visible if they match this condition.`
                                    )}
                                </FormHelperText>
                                <FormFieldErrors
                                    field={'condition'}
                                    errors={errors}
                                />
                            </FormGroup>
                        </FormRow>
                    </Grid>
                    <Grid size={12}>
                        <Button
                            className={'btn-primary'}
                            type={'submit'}
                            disabled={!condition}
                        >
                            {t('common.save', `Save`)}
                        </Button>{' '}
                        <Button
                            className={'btn-secondary'}
                            color={'warning'}
                            onClick={onCancel}
                        >
                            {t('common.cancel', `Cancel`)}
                        </Button>{' '}
                        {data?.id && (
                            <Button
                                sx={{
                                    float: 'right',
                                }}
                                color={'error'}
                                onClick={deleteClick}
                            >
                                {t('common.delete', `Delete`)}
                            </Button>
                        )}
                    </Grid>
                </Grid>
            </Paper>
        </form>
    );
}
