import React, {PureComponent, RefObject} from "react";
import {Form} from "react-bootstrap";
import TagSelect from "../Tag/TagSelect";
import {Tag} from "../../../types";
import Button from "../../ui/Button";
import GroupSelect from "../../User/GroupSelect";
import UserSelect from "../../User/UserSelect";
import {saveTagFilterRule} from "../../../api/tag-filter-rule";

export type FilterRuleProps = {
    id?: string;
    workspaceIdForTags: string;
    userId?: string;
    groupId?: string;
    workspaceId?: string;
    collectionId?: string;
    include: Tag[];
    exclude: Tag[];
};

export default class FilterRule extends PureComponent<FilterRuleProps> {
    private readonly includeRef: RefObject<TagSelect>;
    private readonly excludeRef: RefObject<TagSelect>;
    private readonly userRef: RefObject<UserSelect>;
    private readonly groupRef: RefObject<GroupSelect>;

    constructor(props: FilterRuleProps) {
        super(props);

        this.includeRef = React.createRef();
        this.excludeRef = React.createRef();
        this.userRef = React.createRef();
        this.groupRef = React.createRef();
    }

    save = async () => {
        const include = this.includeRef.current!.getData().map(t => t['@id']);
        const exclude = this.excludeRef.current!.getData().map(t => t['@id']);
        const userId = this.userRef.current!.getValue() || undefined;
        const groupId = this.groupRef.current!.getValue() || undefined;

        await saveTagFilterRule({
            userId,
            groupId,
            include,
            exclude,
            workspaceId: this.props.workspaceId,
            collectionId: this.props.collectionId,
        });
    }

    render() {
        const type = this.props.collectionId ? 'collection' : 'workspace';

        return <Form>
            <div className="row">
                <div className="col-md-12">
                    Rule applies for:
                </div>
            </div>
            <div className={'row mb-3'}>
                <div className="col-md-5">
                    <GroupSelect
                        ref={this.groupRef}
                    />
                </div>
                <div className="col-md-2">
                    <b>or</b>
                </div>
                <div className="col-md-5">
                    <UserSelect
                        ref={this.userRef}
                    />
                </div>
            </div>
            <div className="row">
                <div className="col-md-6">
                    <Form.Group controlId="include">
                        <Form.Label>Tags to <b>include</b></Form.Label>
                        <TagSelect
                            ref={this.includeRef}
                            value={this.props.include}
                            workspaceId={this.props.workspaceIdForTags}
                        />
                        <Form.Text className="text-muted">
                            Assets in this {type} will only be visible if they contains theses tags.
                        </Form.Text>
                    </Form.Group>
                </div>
                <div className="col-md-6">
                    <Form.Group controlId="exclude">
                        <Form.Label>Tags to <b>exclude</b></Form.Label>
                        <TagSelect
                            ref={this.excludeRef}
                            value={this.props.exclude}
                            workspaceId={this.props.workspaceIdForTags}
                        />
                        <Form.Text className="text-muted">
                            Assets in this {type} will only be visible if they DOES NOT contains theses tags.
                        </Form.Text>
                    </Form.Group>
                </div>
            </div>
            <div className="row">
                <div className="col-md-12">
                    <Button
                        className={'btn-primary'}
                        onClick={this.save}
                    >
                        Save
                    </Button>
                </div>
            </div>
        </Form>
    }
}
