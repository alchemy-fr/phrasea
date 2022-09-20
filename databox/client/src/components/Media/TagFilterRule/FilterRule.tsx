import React from "react";
import TagSelect from "../../Form/TagSelect";
import GroupSelect from "../../Form/GroupSelect";
import UserSelect from "../../Form/UserSelect";
import {Button, FormGroup, FormHelperText, FormLabel, Grid, Paper} from "@mui/material";
import {Trans, useTranslation} from "react-i18next";
import {useForm} from "react-hook-form";
import FormRow from "../../Form/FormRow";
import {deleteTagFilterRule, saveTagFilterRule} from "../../../api/tag-filter-rule";
import FormFieldErrors from "../../Form/FormFieldErrors";
import {Group, User} from "../../../types";
import {useDirtyFormPrompt} from "../../Dialog/Tabbed/FormTab";

type FilterRule = {
    id?: string | undefined;
    userId?: string | undefined;
    groupId?: string | undefined;
    include: string[];
    exclude: string[];
};

export type {FilterRule as FilterRuleProps};

export type TagFilterRuleType = "workspace" | "collection";

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
    users?: User[];
    groups?: Group[];
};

export default function FilterRule({
                                       data,
                                       onSubmit,
                                       disabledGroups,
                                       disabledUsers,
                                       type,
                                       onDelete,
                                       onCancel,
                                       users,
                                       groups,
                                       workspaceId,
                                       collectionId,
                                       workspaceIdForTags,
                                   }: Props) {
    const {t} = useTranslation();

    const {
        handleSubmit,
        control,
        formState: {errors, isDirty}
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
    }

    const deleteClick = async () => {
        if (!window.confirm('Confirm delete this rule?')) {
            return;
        }

        const id = data!.id!;
        await deleteTagFilterRule(id);
        onDelete(id);
    }

    return <form
        onSubmit={handleSubmit(saveRule)}
    >
        <Paper
            elevation={2}
            sx={{
                p: 2,
            }}
        >
            <div className="col-md-12">
                Rule applies for:
            </div>
            <Grid container spacing={2}>
                {data?.id ? <Grid item md={12}>
                        <FormRow>
                            <b>
                                {data.userId && `User ${users!.find(i => i.id === data.userId)?.username}`}
                                {data.groupId && `Group ${groups!.find(i => i.id === data.groupId)?.name}`}
                            </b>
                        </FormRow>
                    </Grid>
                    : <><Grid item md={5}>
                        <FormRow>
                            <FormLabel>{t('filter_rule.group', 'Group')}</FormLabel>
                            <GroupSelect
                                name={'groupId'}
                                control={control}
                                disabledValues={disabledGroups}
                            />
                            <FormFieldErrors field={'groupId'} errors={errors}/>
                        </FormRow>
                    </Grid>
                        <Grid item md={2}>
                            <b>or</b>
                        </Grid>
                        <Grid item md={5}>
                            <FormRow>
                                <FormLabel>{t('filter_rule.user', 'User')}</FormLabel>
                                <UserSelect
                                    name={'userId'}
                                    control={control}
                                    disabledValues={disabledUsers}
                                />
                                <FormFieldErrors field={'userId'} errors={errors}/>
                            </FormRow>
                        </Grid>
                    </>
                }
                <Grid item md={6}>
                    <FormRow>
                        <FormGroup>
                            <FormLabel>
                                <Trans key={'filter_rule.include.label'}>
                                    Tags to <b>include</b>
                                </Trans>
                            </FormLabel>
                            <TagSelect
                                name={'include'}
                                control={control}
                                workspaceId={workspaceIdForTags}
                            />
                            <FormHelperText>
                                {t('filter_rule.include.helper', `Assets in this {{type}} will only be visible if they contains theses tags.`, {
                                    type,
                                })}
                            </FormHelperText>
                            <FormFieldErrors field={'include'} errors={errors}/>
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
                                name={'exclude'}
                                control={control}
                                workspaceId={workspaceIdForTags}
                            />
                            <FormHelperText>
                                {t('filter_rule.exclude.helper', `Assets in this {{type}} will only be visible if they DOES NOT contains theses tags.`, {
                                    type,
                                })}
                            </FormHelperText>
                            <FormFieldErrors field={'exclude'} errors={errors}/>
                        </FormGroup>
                    </FormRow>
                </Grid>
                <Grid item md={12}>
                    <Button
                        className={'btn-primary'}
                        type={'submit'}
                    >
                        Save
                    </Button>
                    {' '}
                    <Button
                        className={'btn-secondary'}
                        color={'warning'}
                        onClick={onCancel}
                    >
                        Cancel
                    </Button>
                    {' '}
                    {data?.id && <Button
                        sx={{
                            float: 'right',
                        }}
                        color={'error'}
                        onClick={deleteClick}
                    >
                        Delete
                    </Button>}
                </Grid>
            </Grid>
        </Paper>
    </form>
}
