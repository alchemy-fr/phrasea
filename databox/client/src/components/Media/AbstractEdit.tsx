import React, {PureComponent} from "react";
import Modal from "../Layout/Modal";
import Button from "../ui/Button";
import {IPermissions} from "../../types";
import AclForm from "../Acl/AclForm";

export type AbstractEditProps = {
    id: string,
    onClose: () => void;
}

type State<T extends IPermissions> = {
    loading: boolean;
    saving: boolean;
    data: T | undefined;
};

export default abstract class AbstractEdit<T extends IPermissions> extends PureComponent<AbstractEditProps, State<T>> {
    state = {
        saving: false,
        loading: true,
        data: undefined,
    };

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

    abstract loadItem(): Promise<T>;

    abstract handleSave(): Promise<boolean>;

    renderModalHeader() {
        return <h4>Edit</h4>
    }

    save = (): void => {
        this.setState({saving: true}, async (): Promise<void> => {
            if (!await this.handleSave()) {
                this.setState({saving: false});
                return;
            }

            this.props.onClose();
        });
    }

    render() {
        const {saving} = this.state;

        return <Modal
            onClose={this.props.onClose}
            header={this.renderModalHeader}
            footer={({onClose}) => <>
                <Button
                    onClick={onClose}
                    className={'btn-secondary'}
                    disabled={saving}
                >
                    Close
                </Button>
                <Button
                    onClick={this.save}
                    className={'btn-primary'}
                    disabled={saving}
                >
                    Save changes
                </Button>
            </>}
        >
            {this.renderContent()}
        </Modal>
    }

    renderContent() {
        if (this.state.loading) {
            return 'Loading...';
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
                    objectType={'asset'}
                />
            </div> : ''}
        </div>;
    }

    abstract renderForm(): React.ReactNode;
}
