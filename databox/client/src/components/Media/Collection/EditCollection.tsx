import React, {useEffect, useState} from 'react';
import {Collection} from "../../../types";
import {getCollection, putCollection} from "../../../api/collection";
import {CollectionForm} from "../../Form/CollectionForm";
import EditDialog from "../../Dialog/EditDialog";
import {useTranslation} from "react-i18next";
import {StackedModalProps} from "@mattjennings/react-modal-stack/src/ModalStack";
import FullPageLoader from "../../Ui/FullPageLoader";
import {useModals} from "@mattjennings/react-modal-stack";
import {toast} from "react-toastify";
import useFormSubmit from "../../../hooks/useFormSubmit";
// import TagFilterRules from "../TagFilterRule/TagFilterRules";

export type OnCollectionEdit = (coll: Collection) => void;

type Props = {
    id: string;
    onEdit: OnCollectionEdit;
} & StackedModalProps;

export default function EditCollection({
                                           id,
                                           onEdit,
                                       }: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    const {submitting, handleSubmit, errors} = useFormSubmit({
        onSubmit: async (data: Collection) => {
            return await putCollection(data.id, data);
        },
        onSuccess: (item) => {
            toast.success(t('form.collection_edit.success', 'Collection edited!'))
            closeModal();
            onEdit(item);
        }
    });
    const [data, setData] = useState<Collection>();

    useEffect(() => {
       getCollection(id).then(c => setData(c));
    }, []);

    if (!data) {
        return <FullPageLoader/>
    }

    const formId = 'edit-collection';

    return <EditDialog
        title={t('form.collection_edit.title', 'Edit collection')}
        formId={formId}
        loading={submitting}
        errors={errors}
    >
        <CollectionForm
            data={data}
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
        />
    </EditDialog>
}

//
// export default class EditCollection extends AbstractEdit<Collection, FormProps> {
//     async loadItem() {
//         return await getCollection(this.props.id);
//     }
//
//     getType(): string {
//         return 'collection';
//     }
//
//     getTitle(): ReactNode | null {
//         const d = this.getData();
//         return d ? d.title : null;
//     }
//
//     renderForm(): React.ReactNode {
//         const data: Collection | null = this.getData();
//         if (null === data) {
//             return '';
//         }
//
//         const initialValues: FormProps = {
//             title: data!.title,
//             privacy: data!.privacy,
//         };
//
//         return <div>
//             <Formik
//                 innerRef={this.formRef}
//                 initialValues={initialValues}
//                 onSubmit={(values, actions) => {
//                     this.onSubmit(values, actions);
//                 }}
//             >
//                 <Form>
//                     <div className="form-group">
//                         <Field
//                             component={TextField}
//                             name="title"
//                             type="text"
//                             label="Collection title"
//                         />
//                     </div>
//                     <Field
//                         component={PrivacyField}
//                         name="privacy"
//                     />
//                 </Form>
//             </Formik>
//             <hr/>
//             <div>
//                 <h4>Tag filter rules</h4>
//                 {/*<TagFilterRules*/}
//                 {/*    id={this.props.id}*/}
//                 {/*    workspaceId={this.getData()!.workspace.id}*/}
//                 {/*    type={'collection'}*/}
//                 {/*/>*/}
//             </div>
//         </div>
//     }
//
//     async handleSave(data: FormProps): Promise<boolean> {
//         await patchCollection(this.props.id, data);
//
//         return true;
//     }
// }
