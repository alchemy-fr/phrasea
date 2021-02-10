import {PureComponent} from "react";
import Modal from "../Layout/Modal";
import Button from "../ui/Button";
import {IPermissions} from "../../types";
import AclForm from "../Acl/AclForm";

type Props = {
    id: string,
    onClose: () => void;
}

type State<T extends IPermissions> = {
    loading: boolean;
    data: T | undefined;
};

export default abstract class AbstractEdit<T extends IPermissions> extends PureComponent<Props, State<T>> {
    state = {
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

    renderModalHeader() {
        return <h4>Edit</h4>
    }

    render() {
        return <Modal
            onClose={this.props.onClose}
            header={this.renderModalHeader}
            footer={({onClose}) => <>
                <Button
                    onClick={onClose}
                    className={'btn-secondary'}
                >
                    Close
                </Button>
                <Button
                    onClick={onClose}
                    className={'btn-primary'}
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
            {data.capabilities.canEditPermissions ? <AclForm
                objectId={this.props.id}
                objectType={'asset'}
                /> : ''}
        </div>;
    }

    abstract renderForm(): React.ReactNode;
}
