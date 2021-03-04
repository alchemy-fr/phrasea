import {PureComponent} from "react";
import {TagFilterRule,} from "../../../types";
import {deleteTagFilterRule, getTagFilterRules} from "../../../api/tag-filter-rule";
import FilterRule from "./FilterRule";
import Button from "../../ui/Button";

type Props = {
    id: string;
    type: string;
    workspaceId: string;
};

type State = {
    rules?: TagFilterRule[];
    newRule: boolean;
};

export default class TagFilterRules extends PureComponent<Props, State> {
    state: State = {
        rules: undefined,
        newRule: false,
    };

    componentDidMount() {
        this.loadRules();
    }

    loadRules = async () => {
        const rules = await getTagFilterRules({
            collectionId: this.props.id,
        });

        this.setState({rules: rules.result});
    }

    addRule = () => {
        this.setState({newRule: true});
    }

    cancelRule = () => {
        this.setState({newRule: false});
    }

    deleteRule = async (id: string) => {
        await deleteTagFilterRule(id);
        await this.loadRules();
    }

    render() {
        const {rules, newRule} = this.state;
        if (rules === undefined) {
            return 'Loading rules...';
        }

        return <div>
            {newRule && <div>
                <FilterRule
                    include={[]}
                    exclude={[]}
                    collectionId={this.props.type === 'collection' ? this.props.id : undefined}
                    workspaceId={this.props.type === 'workspace' ? this.props.id : undefined}
                    workspaceIdForTags={this.props.workspaceId}
                />
                <Button
                    className={'btn-danger'}
                    onClick={this.cancelRule}
                >Cancel</Button>
            </div>}
            {!newRule && <Button
                className={'btn-primary'}
                onClick={this.addRule}
            >New rule</Button>}
            <div>
                {rules!.map((r: TagFilterRule) => {
                    return <div>
                        <Button
                            className={'btn-danger'}
                            onClick={this.deleteRule.bind(this, r.id)}
                        >Delete</Button>

                        <FilterRule
                            {...r}
                            workspaceIdForTags={this.props.workspaceId}
                        />
                    </div>
                })}
            </div>
        </div>
    }
}
