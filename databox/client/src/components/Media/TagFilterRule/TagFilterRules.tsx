import {PureComponent} from "react";
import {TagFilterRule,} from "../../../types";
import {getTagFilterRules} from "../../../api/tag-filter-rule";
import FilterRule, {FilterRuleProps} from "./FilterRule";
import Button from "../../ui/Button";
import {Badge} from "react-bootstrap";
import Icon from "../../ui/Icon";
import {ReactComponent as EditImg} from '../../../images/icons/edit.svg';

type Props = {
    id: string;
    type: string;
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
                    include={[]}
                    exclude={[]}
                    disabledUsers={disabledUsers}
                    disabledGroups={disabledGroups}
                    onDelete={this.onDelete}
                    onSave={this.onSave}
                    collectionId={this.props.type === 'collection' ? this.props.id : undefined}
                    workspaceId={this.props.type === 'workspace' ? this.props.id : undefined}
                    workspaceIdForTags={this.props.workspaceId}
                    onCancel={this.onCancel}
                />
            </div>}
            {!newRule && <Button
                className={'btn-primary'}
                onClick={this.addRule}
            >New rule</Button>}
            <div>
                {rules!.map((r: TagFilterRule) => {
                    if (editRule === r.id) {
                        return <div
                            key={r.id}
                        >
                            <FilterRule
                                {...r}
                                workspaceIdForTags={this.props.workspaceId}
                                onDelete={this.onDelete}
                                onSave={this.onSave}
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
                    variant={'success'}
                    key={t.id}
                >{t.name}</Badge>)}
                {rule.exclude.map(t => <Badge
                    variant={'danger'}
                    key={t.id}
                >{t.name}</Badge>)}
            </div>
            <div className="col-md-1">
                <Button
                    onClick={this.editRule.bind(this, rule.id)}
                >
                    <Icon component={EditImg}/>
                </Button>
            </div>
        </div>
    }
}
