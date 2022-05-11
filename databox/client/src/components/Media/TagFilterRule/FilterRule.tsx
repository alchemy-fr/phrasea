import React, {PureComponent, RefObject} from "react";
import TagSelect from "../Tag/TagSelect";
import {Tag, TagFilterRule} from "../../../types";
import GroupSelect from "../../User/GroupSelect";
import UserSelect from "../../User/UserSelect";
import {deleteTagFilterRule, saveTagFilterRule} from "../../../api/tag-filter-rule";

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

type Props = {
    onCancel: () => void;
    onDelete: (id?: string) => void;
    onSave: (data: FilterRuleProps) => void;
    disabledUsers: string[];
    disabledGroups: string[];
} & FilterRuleProps;

export default function FilterRule() {
    return <></>;
}

// export default class FilterRule extends PureComponent<Props> {
//     private readonly includeRef: RefObject<TagSelect>;
//     private readonly excludeRef: RefObject<TagSelect>;
//     private readonly userRef: RefObject<UserSelect>;
//     private readonly groupRef: RefObject<GroupSelect>;
//
//     constructor(props: Props) {
//         super(props);
//
//         this.includeRef = React.createRef();
//         this.excludeRef = React.createRef();
//         this.userRef = React.createRef();
//         this.groupRef = React.createRef();
//     }
//
//     save = async () => {
//         const include = this.includeRef.current!.getData().map(t => t['@id']);
//         const exclude = this.excludeRef.current!.getData().map(t => t['@id']);
//
//         const userId = this.userRef.current ? (this.userRef.current!.getValue() || undefined) : this.props.userId;
//         const groupId = this.groupRef.current ? (this.groupRef.current!.getValue() || undefined) : this.props.groupId;
//
//         const res: TagFilterRule = await saveTagFilterRule({
//             id: this.props.id,
//             userId,
//             groupId,
//             include,
//             exclude,
//             workspaceId: this.props.workspaceId,
//             collectionId: this.props.collectionId,
//         });
//
//         const {workspaceIdForTags} = this.props;
//
//         this.props.onSave({
//             ...res,
//             workspaceIdForTags,
//         });
//     }
//
//     delete = async () => {
//         if (!window.confirm('Confirm delete this rule?')) {
//             return;
//         }
//         const {id} = this.props;
//         if (id) {
//             await deleteTagFilterRule(id);
//         }
//
//         this.props.onDelete(id);
//     }
//
//     cancel = () => {
//         this.props.onCancel();
//     }
//
//     render() {
//         const {
//             collectionId,
//             id,
//             userId,
//             groupId,
//         } = this.props;
//         const type = collectionId ? 'collection' : 'workspace';
//
//         return <div className={'filter-rule'}>
//             {/*<Form>*/}
//             {/*    <div className="row">*/}
//             {/*        <div className="col-md-12">*/}
//             {/*            Rule applies for:*/}
//             {/*        </div>*/}
//             {/*    </div>*/}
//             {/*    {id ? <div className="row">*/}
//             {/*            <div className={'col-md-12 mb-3'}>*/}
//             {/*                <b>*/}
//             {/*                    {userId && `User ${userId}`}*/}
//             {/*                    {groupId && `Group ${groupId}`}*/}
//             {/*                </b>*/}
//             {/*            </div>*/}
//             {/*        </div>*/}
//             {/*        :*/}
//             {/*        <div className={'row mb-3'}>*/}
//             {/*            <div className="col-md-5">*/}
//             {/*                <GroupSelect*/}
//             {/*                    ref={this.groupRef}*/}
//             {/*                    disabledValues={this.props.disabledGroups}*/}
//             {/*                />*/}
//             {/*            </div>*/}
//             {/*            <div className="col-md-2">*/}
//             {/*                <b>or</b>*/}
//             {/*            </div>*/}
//             {/*            <div className="col-md-5">*/}
//             {/*                <UserSelect*/}
//             {/*                    ref={this.userRef}*/}
//             {/*                    disabledValues={this.props.disabledUsers}*/}
//             {/*                />*/}
//             {/*            </div>*/}
//             {/*        </div>}*/}
//             {/*    <div className="row">*/}
//             {/*        <div className="col-md-6">*/}
//             {/*            <Form.Group controlId="include">*/}
//             {/*                <Form.Label>Tags to <b>include</b></Form.Label>*/}
//             {/*                <TagSelect*/}
//             {/*                    ref={this.includeRef}*/}
//             {/*                    value={this.props.include}*/}
//             {/*                    workspaceId={this.props.workspaceIdForTags}*/}
//             {/*                />*/}
//             {/*                <Form.Text className="text-muted">*/}
//             {/*                    Assets in this {type} will only be visible if they contains theses tags.*/}
//             {/*                </Form.Text>*/}
//             {/*            </Form.Group>*/}
//             {/*        </div>*/}
//             {/*        <div className="col-md-6">*/}
//             {/*            <Form.Group controlId="exclude">*/}
//             {/*                <Form.Label>Tags to <b>exclude</b></Form.Label>*/}
//             {/*                <TagSelect*/}
//             {/*                    ref={this.excludeRef}*/}
//             {/*                    value={this.props.exclude}*/}
//             {/*                    workspaceId={this.props.workspaceIdForTags}*/}
//             {/*                />*/}
//             {/*                <Form.Text className="text-muted">*/}
//             {/*                    Assets in this {type} will only be visible if they DOES NOT contains theses tags.*/}
//             {/*                </Form.Text>*/}
//             {/*            </Form.Group>*/}
//             {/*        </div>*/}
//             {/*    </div>*/}
//             {/*    <div className="row">*/}
//             {/*        <div className="col-md-12">*/}
//             {/*            <Button*/}
//             {/*                className={'btn-primary'}*/}
//             {/*                onClick={this.save}*/}
//             {/*            >*/}
//             {/*                Save*/}
//             {/*            </Button>*/}
//             {/*            {' '}*/}
//             {/*            <Button*/}
//             {/*                className={'btn-secondary'}*/}
//             {/*                onClick={this.cancel}*/}
//             {/*            >*/}
//             {/*                Cancel*/}
//             {/*            </Button>*/}
//             {/*            {' '}*/}
//             {/*            {this.props.id && <Button*/}
//             {/*                className={'btn-danger float-right'}*/}
//             {/*                onClick={this.delete}*/}
//             {/*            >*/}
//             {/*                {this.props.id ? 'Delete' : 'Cancel'}*/}
//             {/*            </Button>}*/}
//             {/*        </div>*/}
//             {/*    </div>*/}
//             {/*</Form>*/}
//         </div>
//     }
// }
