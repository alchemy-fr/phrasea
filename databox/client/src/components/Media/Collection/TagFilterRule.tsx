import {PureComponent} from "react";
import Modal from "../../Layout/Modal";
import Button from "../../ui/Button";
import AsyncSelect from 'react-select/async';
import {Form} from "react-bootstrap";
import apiClient from "../../../api/api-client";
import {Tag, User} from "../../../types";

export type TagFilterRuleType = {
    id: string,
    userType: string,
    userId: string;
    objectType: string;
    objectId: string;
    include: string[];
    exclude: string[];
}

export default class TagFilterRule extends PureComponent<TagFilterRuleType> {
    loadTags = async () => {
        const res: {data: Tag[]} = await apiClient.get(`/tags`);

        return res.data.map(t => ({
            label: t.name,
            value: t.id,
        }));
    }

    loadUsers = async () => {
        const res: {data: User[]} = await apiClient.get(`/users`);

        return res.data.map(u => ({
            label: u.username,
            value: u.id,
        }));
    }

    handleInputChange = (newValue: string) => {
        const inputValue = newValue.replace(/\W/g, '');
        this.setState({inputValue});
        return inputValue;
    };

    render() {
           return <Form>
                <Form.Group controlId="include">
                    <Form.Label>Tags to include</Form.Label>
                    <AsyncSelect
                        isMulti
                        cacheOptions
                        loadOptions={this.loadTags}
                        defaultOptions
                    />
                    <Form.Text className="text-muted">
                        Assets in this collection will only be visible if they contains theses tags.
                    </Form.Text>
                </Form.Group>
                <Form.Group controlId="exclude">
                    <Form.Label>Tags to include</Form.Label>
                    <AsyncSelect
                        isMulti
                        cacheOptions
                        loadOptions={this.loadTags}
                        defaultOptions
                    />
                    <Form.Text className="text-muted">
                        Assets in this collection will only be visible if they contains theses tags.
                    </Form.Text>
                </Form.Group>
           </Form>
    }
}
