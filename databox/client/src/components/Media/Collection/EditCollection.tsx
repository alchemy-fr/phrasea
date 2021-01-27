import {PureComponent} from "react";
import Modal from "../../Layout/Modal";
import Button from "../../ui/Button";
import AsyncSelect from 'react-select/async';
import {Form} from "react-bootstrap";
import apiClient from "../../../api/api-client";
import {Tag} from "../../../types";

type Props = {
    id: string,
    onClose: () => void;
}

export default class EditCollection extends PureComponent<Props> {
    loadOptions = async () => {
        const res: {data: Tag[]} = await apiClient.get(`/tags`);

        return res.data.map(t => ({
            label: t.name,
            value: t.id,
        }));
    }

    loadRules = async () => {
        const res = await apiClient.get(`/tags-filter-rules?collection=${this.props.id}`);
    }

    handleInputChange = (newValue: string) => {
        const inputValue = newValue.replace(/\W/g, '');
        this.setState({inputValue});
        return inputValue;
    };

    render() {
        return <Modal
            onClose={this.props.onClose}
            header={() => <h4>Edit</h4>}
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
            <Form>
                <Form.Group controlId="formBasicEmail">
                    <Form.Label>Tags to include</Form.Label>
                    <AsyncSelect
                        isMulti
                        cacheOptions
                        loadOptions={this.loadOptions}
                        defaultOptions
                    />
                    <Form.Text className="text-muted">
                        Assets in this collection will only be visible if they contains theses tags.
                    </Form.Text>
                </Form.Group>
            </Form>
        </Modal>
    }
}
