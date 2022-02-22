import React, {RefObject} from "react";
import AbstractEdit, {AbstractEditProps} from "../AbstractEdit";
import {getAsset, patchAsset} from "../../../api/asset";
import {Asset} from "../../../types";
import TagSelect from "../Tag/TagSelect";
import {Field, Form, Formik} from "formik";
import {TextField} from "formik-material-ui";
import PrivacyField from "../../ui/PrivacyField";
import {InputLabel} from "@material-ui/core";

type FormProps = {
    title: string;
    privacy: number;
}

export default class EditAsset extends AbstractEdit<Asset, FormProps> {
    private readonly tagRef: RefObject<TagSelect>;

    constructor(props: Readonly<AbstractEditProps>) {
        super(props);

        this.tagRef = React.createRef<TagSelect>();
    }

    getType(): string {
        return 'asset';
    }

    getTitle(): string | null {
        const d = this.getData();
        return d ? d.title : null;
    }

    renderForm(): React.ReactNode {
        const data: Asset | null = this.getData();
        if (!data) {
            return '';
        }

        const initialValues: FormProps = {
            title: data.title,
            privacy: data.privacy,
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
                            fullWidth
                            name="title"
                            type="text"
                            label="Asset title"
                        />
                    </div>
                    <Field
                        component={PrivacyField}
                        name="privacy"
                    />
                    <hr/>
                    <div className="form-group">
                        <InputLabel id="demo-controlled-open-select-label">Tags</InputLabel>
                        <TagSelect
                            ref={this.tagRef}
                            value={data.tags}
                            workspaceId={data.workspace.id}
                        />
                    </div>
                </Form>
            </Formik>
        </div>
    }

    async loadItem() {
        return await getAsset(this.props.id);
    }

    async handleSave(data: FormProps): Promise<boolean> {
        await patchAsset(this.props.id, {
            ...data,
            tags: this.tagRef!.current!.getData().map(t => t['@id']),
        });

        return true;
    }
}
