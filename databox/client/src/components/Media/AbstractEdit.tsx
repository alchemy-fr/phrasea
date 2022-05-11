import React, {PureComponent, ReactNode, RefObject} from "react";
import {IPermissions} from "../../types";
import AclForm from "../Acl/AclForm";
import {FormikHelpers, FormikProps} from "formik";
import AppDialog from "../Layout/AppDialog";
import {Button} from "@mui/material";

export type AbstractEditProps = {
    id: string,
    onClose: () => void;
}

type State<T extends IPermissions> = {
    loading: boolean;
    saving: boolean;
    data: T | undefined;
};

export default abstract class AbstractEdit<T extends IPermissions, FP> extends PureComponent<AbstractEditProps, State<T>> {
    protected readonly formRef: RefObject<FormikProps<FP>>;

    state = {
        saving: false,
        loading: true,
        data: undefined,
    };

    constructor(props: AbstractEditProps) {
        super(props);

        this.formRef = React.createRef();
    }

    componentDidMount() {
        this.load();
    }

    async load() {
        const data = await this.loadItem();

        this.setState({
            data,
            loading: false,
        });
    }

    protected abstract loadItem(): Promise<T>;

    protected abstract handleSave(data: FP): Promise<boolean>;

    protected abstract getType(): string;
    protected abstract getTitle(): ReactNode | null;

    protected getSubTitle(): ReactNode | undefined {
        return null;
    }

    getData(): T | null {
        return this.state.data || null;
    }

    renderModalHeader() {
        const title = this.getTitle();
        const subTitle = this.getSubTitle();

        return <div>
            {title && <h4>Edit {title}</h4>}
            {subTitle && <h5>{subTitle}</h5>}
        </div>
    }

    save = (): void => {
        this.formRef.current!.submitForm();
    }

    protected async onSubmit(data: FP, actions: FormikHelpers<FP>) {
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

        return <AppDialog
            loading={saving}
            onClose={this.props.onClose}
            title={this.renderModalHeader()}
            actions={({onClose}) => <>
                <Button
                    onClick={onClose}
                    color={'secondary'}
                    disabled={saving}
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
        </AppDialog>
    }

    renderContent() {
        if (this.state.loading) {
            return 'Loadidng...';
        }

        if (!this.state.data) {
            return 'Not found!';
        }
        const data: T = this.state.data!;

        return <div>
            {this.renderForm()}
            <hr/>
            {data.capabilities.canEditPermissions ? <div>
                <h4>Permissions</h4>
                <AclForm
                    objectId={this.props.id}
                    objectType={this.getType()}
                />
            </div> : ''}
        </div>;
    }

    abstract renderForm(): React.ReactNode;
}
