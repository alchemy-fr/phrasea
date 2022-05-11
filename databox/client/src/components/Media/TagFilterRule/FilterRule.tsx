import React from "react";
import TagSelect from "../Tag/TagSelect";
import GroupSelect from "../../User/GroupSelect";
import UserSelect from "../../User/UserSelect";
import {Button, FormGroup, FormHelperText, FormLabel} from "@mui/material";
import {useTranslation} from "react-i18next";
import {useForm} from "react-hook-form";
import FormRow from "../../Form/FormRow";
import {postTag} from "../../../api/tag";
import {saveTagFilterRule} from "../../../api/tag-filter-rule";

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
        register,
        handleSubmit,
        control,
        formState: {errors}
    } = useForm<any>({
        defaultValues: data,
    });

    const saveRule = async (data: FilterRule) => {
        console.log('data', data);
        await saveTagFilterRule({
            ...data,
            include: data.include.map(id => `/tags/${id}`),
            exclude: data.exclude.map(id => `/tags/${id}`),
            workspaceId,
            collectionId,
        });

        onSubmit(data);
    }

    return <form
        onSubmit={handleSubmit(saveRule)}
    >
        <FormRow>
            <div className="col-md-12">
                Rule applies for:
            </div>
        </FormRow>
        {data?.id ? <FormRow>
                <div className={'col-md-12 mb-3'}>
                    <b>
                        {data?.userId && `User ${data.userId}`}
                        {data?.groupId && `Group ${data.groupId}`}
                    </b>
                </div>
            </FormRow>
            :
            <div className={'row mb-3'}>
                <div className="col-md-5">
                    <GroupSelect
                        name={'groupId'}
                        control={control}
                        disabledValues={disabledGroups}
                    />
                </div>
                <div className="col-md-2">
                    <b>or</b>
                </div>
                <div className="col-md-5">
                    <UserSelect
                        name={'userId'}
                        control={control}
                        disabledValues={disabledUsers}
                    />
                </div>
            </div>}
        <FormRow>
            <div className="col-md-6">
                <FormGroup>
                    <FormLabel>Tags to <b>include</b></FormLabel>
                    <TagSelect
                        name={'include'}
                        control={control}
                        workspaceId={workspaceIdForTags}
                    />
                    <FormHelperText>
                        Assets in this {type} will only be visible if they contains theses tags.
                    </FormHelperText>
                </FormGroup>
            </div>
            <div className="col-md-6">
                <FormGroup>
                    <FormLabel>Tags to <b>exclude</b></FormLabel>
                    <TagSelect
                        name={'exclude'}
                        control={control}
                        workspaceId={workspaceIdForTags}
                    />
                    <FormHelperText>
                        Assets in this {type} will only be visible if they DOES NOT contains theses tags.
                    </FormHelperText>
                </FormGroup>
            </div>
        </FormRow>
        <FormRow>
            <div className="col-md-12">
                <Button
                    className={'btn-primary'}
                    type={'submit'}
                >
                    Save
                </Button>
                {' '}
                <Button
                    className={'btn-secondary'}
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                {' '}
                {data?.id && <Button
                    className={'btn-danger float-right'}
                    onClick={() => onDelete(data!.id)}
                >
                    Delete
                </Button>}
            </div>
        </FormRow>
    </form>
}


// class ssFilterRule extends PureComponent<Props> {
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
//             <Form>
//                 <div className="row">
//                     <div className="col-md-12">
//                         Rule applies for:
//                     </div>
//                 </div>
//                 {id ? <div className="row">
//                         <div className={'col-md-12 mb-3'}>
//                             <b>
//                                 {userId && `User ${userId}`}
//                                 {groupId && `Group ${groupId}`}
//                             </b>
//                         </div>
//                     </div>
//                     :
//                     <div className={'row mb-3'}>
//                         <div className="col-md-5">
//                             <GroupSelect
//                                 ref={this.groupRef}
//                                 disabledValues={this.props.disabledGroups}
//                             />
//                         </div>
//                         <div className="col-md-2">
//                             <b>or</b>
//                         </div>
//                         <div className="col-md-5">
//                             <UserSelect
//                                 ref={this.userRef}
//                                 disabledValues={this.props.disabledUsers}
//                             />
//                         </div>
//                     </div>}
//                 <div className="row">
//                     <div className="col-md-6">
//                         <FormGroup controlId="include">
//                             <FormLabel>Tags to <b>include</b></FormLabel>
//                             <TagSelect
//                                 label={<>Tags to <b>include</b></>}
//                                 ref={this.includeRef}
//                                 value={this.props.include}
//                                 workspaceId={this.props.workspaceIdForTags}
//                             />
//                             <Form.Text className="text-muted">
//                                 Assets in this {type} will only be visible if they contains theses tags.
//                             </Form.Text>
//                         </FormGroup>
//                     </div>
//                     <div className="col-md-6">
//                         <FormGroup controlId="exclude">
//                             <FormLabel>Tags to <b>exclude</b></FormLabel>
//                             <TagSelect
//                                 ref={this.excludeRef}
//                                 value={this.props.exclude}
//                                 workspaceId={this.props.workspaceIdForTags}
//                             />
//                             <Form.Text className="text-muted">
//                                 Assets in this {type} will only be visible if they DOES NOT contains theses tags.
//                             </Form.Text>
//                         </FormGroup>
//                     </div>
//                 </div>
//                 <div className="row">
//                     <div className="col-md-12">
//                         <Button
//                             className={'btn-primary'}
//                             onClick={this.save}
//                         >
//                             Save
//                         </Button>
//                         {' '}
//                         <Button
//                             className={'btn-secondary'}
//                             onClick={this.cancel}
//                         >
//                             Cancel
//                         </Button>
//                         {' '}
//                         {this.props.id && <Button
//                             className={'btn-danger float-right'}
//                             onClick={this.delete}
//                         >
//                             {this.props.id ? 'Delete' : 'Cancel'}
//                         </Button>}
//                     </div>
//                 </div>
//             </Form>
//         </div>
//     }
// }
