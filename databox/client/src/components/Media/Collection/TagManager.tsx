import {FormEvent, PureComponent} from "react";
import {deleteTag, getTags, postTag} from "../../../api/tag";
import {Tag} from "../../../types";
import {Button, IconButton, TextField} from "@mui/material";
import DeleteIcon from '@mui/icons-material/Delete';

type Props = {
    workspaceId: string;
}

type State = {
    tags?: Tag[];
    tagInput: string;
}

export default class TagManager extends PureComponent<Props, State> {
    state: State = {
        tags: undefined,
        tagInput: '',
    };

    componentDidMount() {
        this.loadTags();
    }

    async loadTags() {
        const tags = await getTags({
            workspace: this.props.workspaceId,
        });

        this.setState({tags: tags.result});
    }

    addTag = async (e: FormEvent) => {
        e.preventDefault();
        const tag = this.state.tagInput;

        const res = await postTag({
            name: tag,
            workspaceId: this.props.workspaceId,
        });

        this.setState((prevState: State) => ({
            tags: (prevState.tags || []).concat(res),
            tagInput: '',
        }));
    }

    deleteTag = (id: string) => {
        deleteTag(id);
        this.setState((prevState: State) => ({
            tags: (prevState.tags || []).filter(t => t.id !== id),
        }));
    }

    render() {
        const {tags} = this.state;

        if (!tags) {
            return 'Loading tags...';
        }

        return <div>
            {tags.map(t => {
                return <div
                    key={t.id}
                    className={'row'}
                >
                    <div className="col-md-8">
                        {t.name}
                    </div>
                    <div className="col-md-4">
                        <IconButton
                            size={"small"}
                            onClick={this.deleteTag.bind(this, t.id)}
                        >
                            <DeleteIcon/>
                        </IconButton>
                    </div>
                </div>
            })}
            <div>
                <form onSubmit={this.addTag}>
                    <TextField
                        name={'tag'}
                        label={'Tag name'}
                        onChange={(e) => this.setState({tagInput: e.target.value})}
                        value={this.state.tagInput}
                    />
                    <Button
                        type={'submit'}
                        color={'primary'}
                        disabled={!Boolean(this.state.tagInput)}
                    >
                        Add
                    </Button>
                </form>
            </div>
        </div>
    }
}
