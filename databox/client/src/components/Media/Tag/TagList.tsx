import {PureComponent} from "react";
import apiClient from "../../../api/api-client";
import {Tag} from "../../../types";
import TagForm from "./TagForm";
import AppDialog from "../../Layout/AppDialog";
import {Button} from "@mui/material";

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

        return <AppDialog
            onClose={this.props.onClose}
            title={`Edit</`}
            actions={({onClose}) => <>
                <Button
                    onClick={onClose}
                    color={'secondary'}
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
        </AppDialog>
    }
}
