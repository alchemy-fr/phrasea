import {PureComponent} from "react";
import Modal from "../../Layout/Modal";
import Button from "../../ui/Button";
import apiClient from "../../../api/api-client";
import {Tag} from "../../../types";
import TagForm from "./TagForm";

type Props = {
    onClose: () => void;
}

type State = {
    tags?: Tag[];
    newTag: boolean;
}

export default class TagList extends PureComponent<Props, State> {
    state: State = {
        newTag: false,
    };

    loadTags = async () => {
        const res: Tag[] = await apiClient.get(`/tags`);

        this.setState({
            tags: res,
        });
    }

    componentDidMount() {
        this.loadTags();
    }

    addNewTag = () => {
        this.setState({newTag: true});
    }

    cancelNewTag = () => {
        this.setState({newTag: false});
    }

    render() {
        const {tags, newTag} = this.state;

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
            </>}
        >
            {newTag ? <TagForm
                onSave={this.cancelNewTag}
                onCancel={this.cancelNewTag}

            /> : <Button
                onClick={this.addNewTag}
            >
                Add tag
            </Button>}
            {tags && tags.map(r => <div
                key={r.id}
            >
                {r.name}
            </div>)}
        </Modal>
    }
}
