import React, {useState} from 'react';
import EditDialog from "../../Dialog/EditDialog";
import {StackedModalProps} from "@mattjennings/react-modal-stack/src/ModalStack";
import {CollectionForm} from "../../Form/CollectionForm";
import {Collection} from "../../../types";
import useFormSubmit from "../../../hooks/useFormSubmit";
import {postCollection} from "../../../api/collection";
import {toast} from "react-toastify";
import {useModals} from "@mattjennings/react-modal-stack";
import {OnCollectionEdit} from "./EditCollection";
import {useTranslation} from "react-i18next";

type Props = {
    parent?: string;
    parentTitle?: string;
    workspaceId?: string;
    onCreate: OnCollectionEdit;
} & StackedModalProps;

export default function CreateCollection({
                                             parent,
                                             parentTitle,
                                             workspaceId,
                                             onCreate,
                                         }: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();
    const {
        submitting,
        handleSubmit,
        errors,
    } = useFormSubmit({
        onSubmit: async (data: Collection) => {
            return await postCollection({
                ...data,
                parent,
                workspace: workspaceId ? `/workspaces/${workspaceId}` : undefined,
            });
        },
        onSuccess: (coll) => {
            toast.success(t('form.collection_create.success', 'Collection created!'))
            closeModal();
            onCreate(coll);
        }
    });

    const formId = 'create-collection';

    return <EditDialog
        title={t('form.collection_create.title', 'Create collection')}
        formId={formId}
        loading={submitting}
        errors={errors}
    >
        {parentTitle && <div>
            In {parentTitle}
        </div>}
        <CollectionForm
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
        />
    </EditDialog>
}

// export default class CreateCollection extends PureComponent<Props, State> {
//     protected readonly formRef: RefObject<FormikProps<FormProps>>;
//
//     state = {
//         saving: false,
//     };
//
//     constructor(props: Props) {
//         super(props);
//
//         this.formRef = React.createRef();
//     }
//
//     componentDidMount() {
//     }
//
//     renderModalHeader() {
//         const {parentTitle} = this.props;
//         return <h4>
//             Create new collection
//             {parentTitle ? ` in ${parentTitle}` : ''}
//         </h4>
//     }
//
//     save = (): void => {
//         this.formRef.current!.submitForm();
//     }
//
//     protected async onSubmit(data: FormProps, actions: FormikHelpers<FormProps>) {
//         this.setState({saving: true}, async (): Promise<void> => {
//             const res = await this.handleSave(data);
//             if (!res) {
//                 this.setState({saving: false});
//                 actions.setSubmitting(false);
//                 return;
//             }
//
//             this.props.onClose();
//         });
//     }
//
//     render() {
//         const {saving} = this.state;
//
//         return <AppDialog
//             loading={saving}
//             onClose={this.props.onClose}
//             title={this.renderModalHeader()}
//             actions={({onClose}) => <>
//                 <Button
//                     tabIndex={-1}
//                     onClick={onClose}
//                     disabled={saving}
//                     color={'secondary'}
//                 >
//                     Close
//                 </Button>
//                 <Button
//                     onClick={this.save}
//                     color={'primary'}
//                     disabled={saving}
//                 >
//                     Save changes
//                 </Button>
//             </>}
//         >
//             {this.renderContent()}
//         </AppDialog>
//     }
//
//     validate = (values: FormProps) => {
//         const errors: {title?: string} = {};
//
//         if (!values.title) {
//             errors.title = 'Required';
//         }
//
//         return errors;
//     };
//
//     renderContent() {
//         const initialValues: FormProps = {
//             title: '',
//             privacy: 0,
//         };
//
//         return <div>
//             <Formik
//                 innerRef={this.formRef}
//                 initialValues={initialValues}
//                 onSubmit={(values, actions) => {
//                     this.onSubmit(values, actions);
//                 }}
//                 validate={this.validate}
//             >
//                 <Form>
//                     <div className="form-group">
//                         <Field
//                             component={TextField}
//                             name="title"
//                             type="text"
//                             label="Collection title"
//                             required={true}
//                         />
//                     </div>
//                     <Field
//                         component={PrivacyField}
//                         name="privacy"
//                     />
//                 </Form>
//             </Formik>
//         </div>
//     }
//
//     async handleSave(data: FormProps): Promise<boolean> {
//         await postCollection({
//             parent: this.props.parent,
//             workspace: this.props.workspaceId,
//             ...data,
//         });
//
//         return true;
//     }
// }
