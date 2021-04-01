import {FormEvent, PureComponent} from "react";
import {deleteTag, getTags, postTag} from "../../../api/tag";
import {Tag} from "../../../types";
import {TextField} from "@material-ui/core";
import Button from "../../ui/Button";
import Icon from "../../ui/Icon";
import {ReactComponent as TrashImg} from "../../../images/icons/trash.svg";

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
            workspaceId: this.props.workspaceId,
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
                        <Button
                            size={"sm"}
                            onClick={this.deleteTag.bind(this, t.id)}
                        ><Icon
                            component={TrashImg}
                        /></Button>
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
                        className={'btn-primary'}
                        disabled={!Boolean(this.state.tagInput)}
                    >
                        Add
                    </Button>
                </form>
            </div>
        </div>
    }
}
