import {FormEvent, PureComponent} from "react";
import {deleteTag, getTags, postTag} from "../../../api/tag";
import {Tag} from "../../../types";
import {Button, Grid, IconButton, TextField} from "@mui/material";
import DeleteIcon from '@mui/icons-material/Delete';

type Props = {
    workspaceIri: string;
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
            workspace: this.props.workspaceIri,
        });

        this.setState({tags: tags.result});
    }

    addTag = async (e: FormEvent) => {
        e.preventDefault();
        const tag = this.state.tagInput;

        const res = await postTag({
            name: tag,
            workspaceId: this.props.workspaceIri,
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
                return <Grid container
                             key={t.id}
                >
                    <Grid item md={8}>
                        {t.name}
                    </Grid>
                    <Grid item md={4}>
                        <IconButton
                            color={'error'}
                            size={"small"}
                            onClick={this.deleteTag.bind(this, t.id)}
                        >
                            <DeleteIcon/>
                        </IconButton>
                    </Grid>
                </Grid>
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
                        size={'large'}
                        variant={'contained'}
                        disabled={!Boolean(this.state.tagInput)}
                    >
                        Add
                    </Button>
                </form>
            </div>
        </div>
    }
}
