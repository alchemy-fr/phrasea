import React from 'react';
import AbstractEdit from "../AbstractEdit";
import {Collection} from "../../../types";
import {getCollection, patchCollection} from "../../../api/collection";
import {
    Formik,
    Form,
    Field,
} from 'formik';
import {TextField} from 'formik-material-ui';
import PrivacyField from "../../ui/PrivacyField";
import TagFilterRules from "../TagFilterRule/TagFilterRules";

type FormProps = {
    title: string;
    privacy: number;
}

export default class EditCollection extends AbstractEdit<Collection, FormProps> {
    async loadItem() {
        return await getCollection(this.props.id);
    }

    getType(): string {
        return 'collection';
    }

    getTitle(): string | null {
        const d = this.getData();
        return d ? d.title : null;
    }

    renderForm(): React.ReactNode {
        const data: Collection | null = this.getData();
        if (null === data) {
            return '';
        }

        const initialValues: FormProps = {
            title: data!.title,
            privacy: data!.privacy,
        };

        return <div>
            <Formik
                innerRef={this.formRef}
                initialValues={initialValues}
                onSubmit={(values, actions) => {
                    this.onSubmit(values, actions);
                }}
            >
                <Form>
                    <div className="form-group">
                        <Field
                            component={TextField}
                            name="title"
                            type="text"
                            label="Collection title"
                        />
                    </div>
                    <Field
                        component={PrivacyField}
                        name="privacy"
                    />
                </Form>
            </Formik>
            <hr/>
            <div>
                <h4>Tag filter rules</h4>
                <TagFilterRules
                    id={this.props.id}
                    workspaceId={this.getData()!.workspace.id}
                    type={'collection'}
                />
            </div>
        </div>
    }

    async handleSave(data: FormProps): Promise<boolean> {
        await patchCollection(this.props.id, data);

        return true;
    }
}
