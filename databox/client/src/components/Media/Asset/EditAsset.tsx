import React, {useEffect, useState} from 'react';
import {Asset, Collection} from "../../../types";
import {getCollection, putCollection} from "../../../api/collection";
import {CollectionForm} from "../../Form/CollectionForm";
import FormDialog from "../../Dialog/FormDialog";
import {useTranslation} from "react-i18next";
import {StackedModalProps} from "@mattjennings/react-modal-stack/src/ModalStack";
import FullPageLoader from "../../Ui/FullPageLoader";
import {useModals} from "@mattjennings/react-modal-stack";
import {toast} from "react-toastify";
import useFormSubmit from "../../../hooks/useFormSubmit";
import {AssetForm} from "../../Form/AssetForm";
import {getAsset, putAsset} from "../../../api/asset";
// import TagFilterRules from "../TagFilterRule/TagFilterRules";

export type OnAssetEdit = (coll: Asset) => void;

type Props = {
    id: string;
    onEdit: OnAssetEdit;
} & StackedModalProps;

export default function EditAsset({
                                           id,
                                           onEdit,
                                       }: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    const {submitting, handleSubmit, errors} = useFormSubmit({
        onSubmit: async (data: Asset) => {
            return await putAsset(data.id, data);
        },
        onSuccess: (item) => {
            toast.success(t('form.asset_edit.success', 'Asset edited!'))
            closeModal();
            onEdit(item);
        }
    });
    const [data, setData] = useState<Asset>();

    useEffect(() => {
        getAsset(id).then(c => setData(c));
    }, []);

    if (!data) {
        return <FullPageLoader/>
    }

    const formId = 'edit-asset';

    return <FormDialog
        title={t('form.asset_edit.title', 'Edit asset')}
        formId={formId}
        loading={submitting}
        errors={errors}
    >
        <AssetForm
            data={data}
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
        />
    </FormDialog>

    // return <FormDrawer
    //     id={id}
    //     load={(id) => getCollection(id)}
    //     onClose={onClose}
    //     onChange={onChange}
    //     onSubmit={onSubmit}
    //     form={CollectionForm}
    // />

}


//
// export default class EditAsset extends AbstractEdit<Asset, FormProps> {
//     private readonly tagRef: RefObject<TagSelect>;
//
//     constructor(props: Readonly<AbstractEditProps>) {
//         super(props);
//
//         this.tagRef = React.createRef<TagSelect>();
//     }
//
//     getType(): string {
//         return 'asset';
//     }
//
//     getTitle(): ReactNode | null {
//         const d = this.getData();
//
//         return d ? d.title : null;
//     }
//
//     protected getSubTitle(): React.ReactNode | undefined {
//         const d = this.getData();
//
//         if (d) {
//             return <div style={{
//                 fontSize: 15
//             }}>
//                 {d.collections.length > 0 && <div>
//                     {`In collections : `}
//                         {d.collections.map(c => {
//                             return <div className={'badge badge-secondary'}>
//                                 <Icon
//                                     variant={'xs'}
//                                     component={FolderImg}/>
//                                 {c.title}
//                             </div>
//                         })}
//                 </div>}
//                 <div>{`Workspace : `}
//                     <div className={'badge badge-primary'}>
//                     {d.workspace.name}
//                     </div>
//                 </div>
//             </div>
//         }
//     }
//
//     renderForm(): React.ReactNode {
//         // const data: Asset | null = this.getData();
//         // if (!data) {
//         //     return '';
//         // }
//         //
//         // const initialValues: FormProps = {
//         //     title: data.title,
//         //     privacy: data.privacy,
//         // };
//         //
//         // return <div>
//         //     <Formik
//         //         innerRef={this.formRef}
//         //         initialValues={initialValues}
//         //         onSubmit={(values, actions) => {
//         //             this.onSubmit(values, actions);
//         //         }}
//         //     >
//         //         <Form>
//         //             <div className="form-group">
//         //                 <Field
//         //                     component={TextField}
//         //                     fullWidth
//         //                     name="title"
//         //                     type="text"
//         //                     label="Asset title"
//         //                 />
//         //             </div>
//         //             <Field
//         //                 component={PrivacyField}
//         //                 name="privacy"
//         //             />
//         //             <hr/>
//         //             <div className="form-group">
//         //                 <InputLabel id="demo-controlled-open-select-label">Tags</InputLabel>
//         //                 <AsyncSelectWidget
//         //                     value={this.state.value}
//         //                     onChange={this.onChange}
//         //                     defaultOptions
//         //                     load={this.loadTags}
//         //                 />
//         //                 <TagSelect
//         //                     ref={this.tagRef}
//         //                     value={data.tags}
//         //                     workspaceId={data.workspace.id}
//         //                 />
//         //             </div>
//         //         </Form>
//         //     </Formik>
//         // </div>
//
//         return '';
//     }
//
//     async loadItem() {
//         return await getAsset(this.props.id);
//     }
//
//     async handleSave(data: FormProps): Promise<boolean> {
//         await patchAsset(this.props.id, {
//             ...data,
//             tags: this.tagRef!.current!.getData().map(t => t['@id']),
//         });
//
//         return true;
//     }
// }
