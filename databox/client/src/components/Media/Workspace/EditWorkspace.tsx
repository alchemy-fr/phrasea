import {Workspace} from "../../../types";
import {getWorkspace} from "../../../api/workspace";
import AbstractEdit from "../AbstractEdit";
import React, {ReactNode} from "react";
import {Field, Form, Formik} from "formik";
import {TextField} from "formik-material-ui";
import {patchWorkspace} from "../../../api/collection";
import TagManager from "../Collection/TagManager";
import TagFilterRules from "../TagFilterRule/TagFilterRules";

type FormProps = {
    name: string;
}

export default class EditWorkspace extends AbstractEdit<Workspace, FormProps> {
    async loadItem(): Promise<Workspace> {
        return await getWorkspace(this.props.id);
    }

    getTitle(): ReactNode | null {
        const d = this.getData();
        return d ? d.name : null;
    }

    getType(): string {
        return 'workspace';
    }


    renderForm(): React.ReactNode {
        const data: Workspace | null = this.getData();
        if (null === data) {
            return '';
        }

        const initialValues: FormProps = {
            name: data!.name,
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
                    <Field
                        component={TextField}
                        name="name"
                        type="text"
                        label="Workspace name"
                    />
                </Form>
            </Formik>
            <hr/>
            <div>
                <h4>Manage tags</h4>
                <TagManager workspaceId={this.getData()!['@id']}/>
            </div>
            <hr/>
            <div>
                <h4>Tag filter rules</h4>
                <TagFilterRules
                    id={this.props.id}
                    workspaceId={this.props.id}
                    type={'workspace'}
                />
            </div>
        </div>
    }

    async handleSave(data: FormProps): Promise<boolean> {
        await patchWorkspace(this.props.id, data);

        return true;
    }
}
