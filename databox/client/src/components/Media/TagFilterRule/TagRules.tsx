import React from 'react';
import {TagFilterRule} from '../../../types';
import {getTagFilterRules} from '../../../api/tag-filter-rule';
import FilterRule, {TagFilterRuleType} from './FilterRule';
import {
    Box,
    Button,
    Chip,
    Grid,
    IconButton,
    Paper,
    Tooltip,
    Typography,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import AddIcon from '@mui/icons-material/Add';
import {tagNS} from '../../../api/tag';
import FullPageLoader from '../../Ui/FullPageLoader.tsx';
import GroupIcon from '@mui/icons-material/Group';
import { useTranslation } from 'react-i18next';

type Props = {
    id: string;
    type: TagFilterRuleType;
    workspaceId: string;
};

export default function TagRules({type, workspaceId, id}: Props) {
    const {t} = useTranslation();
    const [rules, setRules] = React.useState<TagFilterRule[]>();
    const [newRule, setNewRule] = React.useState(false);
    const [editRule, setEditRule] = React.useState<string | undefined>();

    const loadRules = React.useCallback(() => {
        getTagFilterRules({
            collectionId: type === 'collection' ? id : undefined,
            workspaceId: type === 'workspace' ? id : undefined,
        }).then(d => setRules(d.result));
    }, [type, id, workspaceId]);

    React.useEffect(() => {
        loadRules();
    }, [loadRules]);

    const addRule = React.useCallback(() => {
        setNewRule(true);
        setEditRule(undefined);
    }, []);

    const onCancel = React.useCallback(() => {
        setNewRule(false);
        setEditRule(undefined);
    }, []);

    const onEditRule = React.useCallback((id: string) => {
        setNewRule(false);
        setEditRule(id);
    }, []);

    const refresh = React.useCallback(() => {
        onCancel();
        loadRules();
    }, [onCancel, loadRules]);

    if (!rules) {
        return <FullPageLoader />;
    }

    const disabledUsers = rules.filter(r => !!r.userId).map(r => r.userId!);
    const disabledGroups = rules.filter(r => !!r.groupId).map(r => r.groupId!);

    return (
        <>
            <Typography variant={'h2'}>{t('tag_rules.tag_rules', `Tag rules`)}</Typography>
            <div>
                {newRule && (
                    <FilterRule
                        type={type}
                        disabledUsers={disabledUsers}
                        disabledGroups={disabledGroups}
                        onDelete={refresh}
                        onSubmit={refresh}
                        collectionId={type === 'collection' ? id : undefined}
                        workspaceId={type === 'workspace' ? id : undefined}
                        workspaceIdForTags={workspaceId}
                        onCancel={onCancel}
                    />
                )}
                {!newRule && (
                    <div>
                        <Button
                            startIcon={<AddIcon />}
                            color={'primary'}
                            onClick={addRule}
                        >
                            New rule
                        </Button>
                    </div>
                )}
                <div>
                    {rules!.map((r: TagFilterRule) => {
                        if (editRule === r.id) {
                            return (
                                <Box sx={{mt: 2}}>
                                    <FilterRule
                                        key={r.id}
                                        data={{
                                            ...r,
                                            include: r.include.map(
                                                i => `${tagNS}/${i.id}`
                                            ),
                                            exclude: r.exclude.map(
                                                i => `${tagNS}/${i.id}`
                                            ),
                                        }}
                                        type={type}
                                        workspaceIdForTags={workspaceId}
                                        onDelete={refresh}
                                        onSubmit={refresh}
                                        onCancel={onCancel}
                                        disabledUsers={disabledUsers}
                                        disabledGroups={disabledGroups}
                                    />
                                </Box>
                            );
                        } else {
                            return (
                                <Paper elevation={2} sx={{p: 2, mt: 2}}>
                                    <Grid container spacing={2} key={r.id}>
                                        <Grid item md={4}>
                                            <Chip
                                                icon={
                                                    r.groupName ? (
                                                        <GroupIcon />
                                                    ) : undefined
                                                }
                                                label={
                                                    r.username ?? r.groupName
                                                }
                                            />
                                        </Grid>
                                        <Grid
                                            item
                                            md={7}
                                            sx={{
                                                '.MuiChip-root': {
                                                    ml: 1,
                                                },
                                            }}
                                        >
                                            <span>
                                                {r.include.map(t => (
                                                    <Chip
                                                        color={'success'}
                                                        key={t.id}
                                                        label={t.nameTranslated}
                                                    />
                                                ))}
                                            </span>
                                            <span>
                                                {r.exclude.map(t => (
                                                    <Chip
                                                        color={'error'}
                                                        key={t.id}
                                                        label={t.nameTranslated}
                                                    />
                                                ))}
                                            </span>
                                        </Grid>
                                        <Grid item md={1}>
                                            <Tooltip title={t('tag_rules.edit_this_rule', `Edit this rule`)}>
                                                <IconButton
                                                    onClick={() =>
                                                        onEditRule(r.id)
                                                    }
                                                >
                                                    <EditIcon />
                                                </IconButton>
                                            </Tooltip>
                                        </Grid>
                                    </Grid>
                                </Paper>
                            );
                        }
                    })}
                </div>
            </div>
        </>
    );
}
