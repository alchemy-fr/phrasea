import React, {PureComponent, RefObject} from 'react';
import {
    Formik,
    Form,
    Field, FormikProps, FormikHelpers,
} from 'formik';
import {TextField} from 'formik-material-ui';
import PrivacyField from "../../ui/PrivacyField";
import Modal from "../../Layout/Modal";
import {Button} from "@material-ui/core";
import {postAsset} from "../../../api/asset";

type Props = {
    onClose: () => void;
    collectionId?: string;
    workspaceId?: string;
    collectionTitle?: string;
};

type State = {
    saving: boolean;
};

type FormProps = {
    title: string;
    privacy: number;
};

export default class CreateAsset extends PureComponent<Props, State> {
    protected readonly formRef: RefObject<FormikProps<FormProps>>;

    state = {
        saving: false,
    };

    constructor(props: Props) {
        super(props);

        this.formRef = React.createRef();
    }

    componentDidMount() {
    }

    renderModalHeader() {
        const {collectionTitle} = this.props;
        return <h4>
            Create new asset
            {collectionTitle && ` in ${collectionTitle}`}
        </h4>
    }

    save = (): void => {
        this.formRef.current!.submitForm();
    }

    protected async onSubmit(data: FormProps, actions: FormikHelpers<FormProps>) {
        this.setState({saving: true}, async (): Promise<void> => {
            const res = await this.handleSave(data);
            if (!res) {
                this.setState({saving: false});
                actions.setSubmitting(false);
                return;
            }

            this.props.onClose();
        });
    }

    render() {
        const {saving} = this.state;

        return <Modal
            loading={saving}
            onClose={this.props.onClose}
            header={this.renderModalHeader.bind(this)}
            footer={({onClose}) => <>
                <Button
                    tabIndex={-1}
                    onClick={onClose}
                    disabled={saving}
                    color={'secondary'}
                >
                    Close
                </Button>
                <Button
                    onClick={this.save}
                    color={'primary'}
                    disabled={saving}
                >
                    Save changes
                </Button>
            </>}
        >
            {this.renderContent()}
        </Modal>
    }

    validate = (values: FormProps) => {
        const errors: {title?: string} = {};

        if (!values.title) {
            errors.title = 'Required';
        }

        return errors;
    };

    renderContent() {
        const initialValues: FormProps = {
            title: '',
            privacy: 0,
        };

        return <div>
            <Formik
                innerRef={this.formRef}
                initialValues={initialValues}
                onSubmit={(values, actions) => {
                    this.onSubmit(values, actions);
                }}
                validate={this.validate}
            >
                <Form>
                    <div className="form-group">
                        <Field
                            component={TextField}
                            name="title"
                            type="text"
                            label="Asset title"
                            required={true}
                        />
                    </div>
                    <Field
                        component={PrivacyField}
                        name="privacy"
                    />
                </Form>
            </Formik>
        </div>
    }

    async handleSave(data: FormProps): Promise<boolean> {
        await postAsset({
            collection: this.props.collectionId,
            workspace: this.props.workspaceId,
            ...data,
        });

        return true;
    }
}
