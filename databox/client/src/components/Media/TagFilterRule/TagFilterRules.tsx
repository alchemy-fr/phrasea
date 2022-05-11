import {PureComponent} from "react";
import {TagFilterRule,} from "../../../types";
import {getTagFilterRules} from "../../../api/tag-filter-rule";
import FilterRule, {FilterRuleProps, TagFilterRuleType} from "./FilterRule";
import {Badge, Button} from "@mui/material";
import EditIcon from '@mui/icons-material/Edit';

type Props = {
    id: string;
    type: TagFilterRuleType;
    workspaceId: string;
};

type State = {
    rules?: TagFilterRule[];
    newRule: boolean;
    editRule: string | null;
};

export default class TagFilterRules extends PureComponent<Props, State> {
    state: State = {
        rules: undefined,
        newRule: false,
        editRule: null,
    };

    componentDidMount() {
        this.loadRules();
    }

    loadRules = async () => {
        const {type} = this.props;
        const rules = await getTagFilterRules({
            collectionId: type === 'collection' ? this.props.id : undefined,
            workspaceId: type === 'workspace' ? this.props.id : undefined,
        });

        this.setState({rules: rules.result});
    }

    addRule = () => {
        this.setState({newRule: true, editRule: null});
    }

    onCancel = async () => {
        this.setState({newRule: false, editRule: null});
    }

    onDelete = async (id?: string) => {
        this.setState({newRule: false, editRule: null});
        await this.loadRules();
    }

    onSave = async (data: FilterRuleProps) => {
        this.setState({newRule: false, editRule: null});
        await this.loadRules();
    }

    editRule = async (id: string) => {
        this.setState({editRule: id, newRule: false});
    }

    render() {
        const {rules, newRule, editRule} = this.state;
        if (rules === undefined) {
            return 'Loading rules...';
        }

        const disabledUsers = rules.filter(r => !!r.userId).map(r => r.userId!);
        const disabledGroups = rules.filter(r => !!r.groupId).map(r => r.groupId!);

        return <div>
            {newRule && <div>
                <FilterRule
                    type={this.props.type}
                    disabledUsers={disabledUsers}
                    disabledGroups={disabledGroups}
                    onDelete={this.onDelete}
                    onSubmit={this.onSave}
                    collectionId={this.props.type === 'collection' ? this.props.id : undefined}
                    workspaceId={this.props.type === 'workspace' ? this.props.id : undefined}
                    workspaceIdForTags={this.props.workspaceId}
                    onCancel={this.onCancel}
                />
            </div>}
            {!newRule && <Button
                color={'primary'}
                onClick={this.addRule}
            >New rule</Button>}
            <div>
                {rules!.map((r: TagFilterRule) => {
                    if (editRule === r.id) {
                        return <div
                            key={r.id}
                        >
                            <FilterRule
                                data={{
                                    ...r,
                                    include: r.include.map(i => i.id),
                                    exclude: r.exclude.map(i => i.id),
                                }}
                                type={this.props.type}
                                workspaceIdForTags={this.props.workspaceId}
                                onDelete={this.onDelete}
                                onSubmit={this.onSave}
                                onCancel={this.onCancel}
                                disabledUsers={disabledUsers}
                                disabledGroups={disabledGroups}
                            />
                        </div>
                    } else {
                        return this.renderRule(r);
                    }
                })}
            </div>
        </div>
    }

    renderRule(rule: TagFilterRule) {
        return <div
            className={'row filter-rule'}
            key={rule.id}
        >
            <div className="col-md-4">
                {rule.userId && `User ${rule.userId}`}
                {rule.groupId && `Group ${rule.groupId}`}
            </div>
            <div className="col-md-7 tag-container tag-inc-excl">
                {rule.include.map(t => <Badge
                    color={'success'}
                    key={t.id}
                >{t.name}</Badge>)}
                {rule.exclude.map(t => <Badge
                    color={'error'}
                    key={t.id}
                >{t.name}</Badge>)}
            </div>
            <div className="col-md-1">
                <Button
                    onClick={this.editRule.bind(this, rule.id)}
                >
                    <EditIcon />
                </Button>
            </div>
        </div>
    }
}
