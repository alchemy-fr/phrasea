import {PureComponent} from "react";
import Modal from "../../Layout/Modal";
import Button from "../../ui/Button";
import {Form} from "react-bootstrap";

type Props = {
    id?: string;
    onSave: () => void;
    onCancel: () => void;
}

export default class TagForm extends PureComponent<Props> {
    render() {
        const isNew = !!this.props.id;

        return <Modal
            onClose={this.props.onCancel}
            header={() => <h4>{isNew ? 'New tag' : 'Edit tag'}</h4>}
            footer={({onClose}) => <>
                <Button
                    onClick={onClose}
                    className={'btn-secondary'}
                >
                    Close
                </Button>
            </>}
        >
            <Form>
            <Form.Group controlId="include">
                <Form.Label>Tag name</Form.Label>
                <Form.Control type={'text'} />
                <Form.Text className="text-muted">
                    Assets in this collection will only be visible if they contains theses tags.
                </Form.Text>
            </Form.Group>
        </Form>
        </Modal>
    }
}
