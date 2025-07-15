import TagSelect from '../../Form/TagSelect';
import GroupSelect from '../../Form/GroupSelect';
import UserSelect from '../../Form/UserSelect';
import {
    Button,
    Chip,
    FormGroup,
    FormHelperText,
    FormLabel,
    Grid,
    Paper,
} from '@mui/material';
import {Trans, useTranslation} from 'react-i18next';
import {useForm} from 'react-hook-form';
import {FormRow} from '@alchemy/react-form';
import {
    deleteTagFilterRule,
    saveTagFilterRule,
} from '../../../api/tag-filter-rule';
import {FormFieldErrors} from '@alchemy/react-form';
import {TagFilterRule} from '../../../types';
import {useDirtyFormPrompt} from '../../Dialog/Tabbed/FormTab';
import GroupIcon from '@mui/icons-material/Group';

type FilterRule = {
    id?: string | undefined;
    include: string[];
    exclude: string[];
} & Omit<TagFilterRule, 'id' | 'include' | 'exclude'>;

export type TagFilterRuleType = 'workspace' | 'collection';

type Props = {
    data?: FilterRule | undefined;
    onCancel: () => void;
    onDelete: (id?: string) => void;
    onSubmit: (data: FilterRule) => void;
    disabledUsers: string[];
    disabledGroups: string[];
    type: TagFilterRuleType;
    workspaceId?: string;
    collectionId?: string;
    workspaceIdForTags: string;
};

export default function FilterRule({
    data,
    onSubmit,
    disabledGroups,
    disabledUsers,
    type,
    onDelete,
    onCancel,
    workspaceId,
    collectionId,
    workspaceIdForTags,
}: Props) {
    const {t} = useTranslation();

    const {
        handleSubmit,
        control,
        formState: {errors, isDirty},
    } = useForm<any>({
        defaultValues: data,
    });
    useDirtyFormPrompt(isDirty);

    const saveRule = async (data: FilterRule) => {
        await saveTagFilterRule({
            ...data,
            workspaceId,
            collectionId,
        });

        onSubmit(data);
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
        await deleteTagFilterRule(id);
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
                    {data?.id ? (
                        <Grid item md={12}>
                            <FormRow>
                                <Chip
                                    icon={
                                        data.groupName ? (
                                            <GroupIcon />
                                        ) : undefined
                                    }
                                    label={data.username ?? data.groupName}
                                />
                            </FormRow>
                        </Grid>
                    ) : (
                        <>
                            <Grid item md={5}>
                                <FormRow>
                                    <FormLabel>
                                        {t('filter_rule.group', 'Group')}
                                    </FormLabel>
                                    <GroupSelect
                                        name={'groupId'}
                                        control={control}
                                        disabledValues={disabledGroups}
                                    />
                                    <FormFieldErrors
                                        field={'groupId'}
                                        errors={errors}
                                    />
                                </FormRow>
                            </Grid>
                            <Grid item md={2}>
                                <b>{t('filter_rule.or', `or`)}</b>
                            </Grid>
                            <Grid item md={5}>
                                <FormRow>
                                    <FormLabel>
                                        {t('filter_rule.user', 'User')}
                                    </FormLabel>
                                    <UserSelect
                                        name={'userId'}
                                        control={control}
                                        disabledValues={disabledUsers}
                                    />
                                    <FormFieldErrors
                                        field={'userId'}
                                        errors={errors}
                                    />
                                </FormRow>
                            </Grid>
                        </>
                    )}
                    <Grid item md={6}>
                        <FormRow>
                            <FormGroup>
                                <FormLabel>
                                    <Trans i18nKey="filter_rule.include.label">
                                        Tags to <b>include</b>
                                    </Trans>
                                </FormLabel>
                                <TagSelect
                                    multiple={true}
                                    name={'include'}
                                    control={control}
                                    workspaceId={workspaceIdForTags}
                                />
                                <FormHelperText>
                                    {t(
                                        'filter_rule.include.helper',
                                        `Assets in this {{type}} will only be visible if they contains theses tags.`,
                                        {
                                            type,
                                        }
                                    )}
                                </FormHelperText>
                                <FormFieldErrors
                                    field={'include'}
                                    errors={errors}
                                />
                            </FormGroup>
                        </FormRow>
                    </Grid>
                    <Grid item md={6}>
                        <FormRow>
                            <FormGroup>
                                <FormLabel>
                                    <Trans key={'filter_rule.exclude.label'}>
                                        Tags to <b>exclude</b>
                                    </Trans>
                                </FormLabel>
                                <TagSelect
                                    multiple={true}
                                    name={'exclude'}
                                    control={control}
                                    workspaceId={workspaceIdForTags}
                                />
                                <FormHelperText>
                                    {t(
                                        'filter_rule.exclude.helper',
                                        `Assets in this {{type}} will only be visible if they DOES NOT contains theses tags.`,
                                        {
                                            type,
                                        }
                                    )}
                                </FormHelperText>
                                <FormFieldErrors
                                    field={'exclude'}
                                    errors={errors}
                                />
                            </FormGroup>
                        </FormRow>
                    </Grid>
                    <Grid item md={12}>
                        <Button className={'btn-primary'} type={'submit'}>
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
