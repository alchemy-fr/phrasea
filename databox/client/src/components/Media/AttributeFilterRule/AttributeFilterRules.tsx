import React from 'react';
import {AttributeFilterRule} from '../../../types';
import {getAttributeFilterRules} from '../../../api/attribute-filter-rule';
import FilterRuleForm from './FilterRuleForm';
import {
    Box,
    Button,
    Chip,
    Grid2 as Grid,
    IconButton,
    Paper,
    Tooltip,
    Typography,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import AddIcon from '@mui/icons-material/Add';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import GroupIcon from '@mui/icons-material/Group';
import PublicIcon from '@mui/icons-material/Public';
import {useTranslation} from 'react-i18next';

type Props = {
    workspaceId: string;
};

export default function AttributeFilterRules({workspaceId}: Props) {
    const {t} = useTranslation();
    const [rules, setRules] = React.useState<AttributeFilterRule[]>();
    const [newRule, setNewRule] = React.useState(false);
    const [editRule, setEditRule] = React.useState<string | undefined>();

    const loadRules = React.useCallback(() => {
        getAttributeFilterRules({
            workspaceId,
        }).then(d => setRules(d.result));
    }, [workspaceId]);

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

    return (
        <>
            <Typography variant={'h2'}>
                {t('filter_rules.filter_rules', `Filter rules`)}
            </Typography>
            <div>
                {newRule && (
                    <FilterRuleForm
                        onDelete={refresh}
                        onSubmit={refresh}
                        workspaceId={workspaceId}
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
                            {t('filter_rules.new_rule', `New rule`)}
                        </Button>
                    </div>
                )}
                <div>
                    {rules!.map((r: AttributeFilterRule) => {
                        if (editRule === r.id) {
                            return (
                                <Box sx={{mt: 2}} key={r.id}>
                                    <FilterRuleForm
                                        data={r}
                                        workspaceId={workspaceId}
                                        onDelete={refresh}
                                        onSubmit={refresh}
                                        onCancel={onCancel}
                                    />
                                </Box>
                            );
                        } else {
                            const hasTargets =
                                r.users.length > 0 || r.groups.length > 0;

                            return (
                                <Paper
                                    elevation={2}
                                    sx={{p: 2, mt: 2}}
                                    key={r.id}
                                >
                                    <Grid container spacing={2}>
                                        <Grid
                                            size={4}
                                            sx={{
                                                '.MuiChip-root': {
                                                    mr: 1,
                                                    mb: 1,
                                                },
                                            }}
                                        >
                                            {r.users.map(u => (
                                                <Chip
                                                    key={u.id}
                                                    label={u.name}
                                                />
                                            ))}
                                            {r.groups.map(g => (
                                                <Chip
                                                    key={g.id}
                                                    icon={<GroupIcon />}
                                                    label={g.name}
                                                />
                                            ))}
                                            {!hasTargets && (
                                                <Chip
                                                    icon={<PublicIcon />}
                                                    label={t(
                                                        'filter_rules.everyone',
                                                        `Everyone`
                                                    )}
                                                />
                                            )}
                                        </Grid>
                                        <Grid size={7}>
                                            <code>{r.condition}</code>
                                        </Grid>
                                        <Grid size={1}>
                                            <Tooltip
                                                title={t(
                                                    'filter_rules.edit_this_rule',
                                                    `Edit this rule`
                                                )}
                                            >
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
