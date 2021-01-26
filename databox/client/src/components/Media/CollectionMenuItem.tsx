import {PureComponent, MouseEvent} from "react";
import {Collection} from "../../types";
import {getCollections} from "../../api/collection";
import {ReactComponent as ArrowDownImg} from '../../images/icons/arrow-down.svg';
import Icon from "../ui/Icon";
import Button from "../ui/Button";
import apiClient from "../../api/api-client";

type State = {
    collections?: Collection[],
    expanded: boolean,
}

export type CollectionMenuItemProps = {
    level: number;
    onClick: Function,
    absolutePath: string,
    selectedPath?: string,
} & Collection;

export default class CollectionMenuItem extends PureComponent<CollectionMenuItemProps, State> {
    state: State = {
        expanded: false,
    };

    expandCollection = async (force = false): Promise<void> => {
        this.setState((prevState: State) => {
            return {
                expanded: !prevState.expanded || force,
            };
        }, async (): Promise<void> => {
            if (this.state.expanded) {

                const data = await getCollections({
                    parent: this.props.id,
                });
                this.setState({collections: data});
            }
        });
    }

    onClick = (e: MouseEvent): void => {
        const {onClick} = this.props;

        onClick && onClick(this.props, e);

        this.expandCollection(true);
    }

    onExpandClick = (e: MouseEvent) => {
        e.stopPropagation();
        this.expandCollection();
    }

    delete = () => {
        apiClient.delete(`/collections/${this.props.id}`);
    }

    render() {
        const {
            title,
            children,
            absolutePath,
            selectedPath,
            capabilities,
            level,
        } = this.props;

        const selected = selectedPath === absolutePath;
        const currentInSelectedHierarchy = selectedPath && selectedPath.startsWith(absolutePath);

        return <div
            className={`collection-menu-wrapper`}
        >
            <div
                onClick={this.onClick}
                className={`collection-menu-item ${this.state.expanded ? 'expanded' : ''} ${selected ? 'selected' : ''} ${currentInSelectedHierarchy ? 'current' : ''}`}

            >
                <div
                    className="c-title"
                    style={{
                        paddingLeft: (level * 10),
                    }}
                >
                    {title}
                </div>
                <div className="actions">
                    {capabilities.canEdit ? <Button
                        className={'btn-secondary'}
                        disabled={true}
                    >E</Button> : ''}
                    {capabilities.canDelete ? <Button
                        onClick={this.delete}
                        className={'btn-danger'}
                    >D</Button> : ''}
                </div>
                {children!.length > 0 ? <div
                    className="expand"
                    onClick={this.onExpandClick}
                >
                    <Icon component={ArrowDownImg}/>
                </div> : ''}
            </div>
            {this.renderChildren()}
        </div>
    }

    renderChildren() {
        const {collections, expanded} = this.state;
        if (!expanded || !collections) {
            return '';
        }

        return <div className="sub-colls">
            {collections.map(c => <CollectionMenuItem
                {...c}
                absolutePath={`${this.props.absolutePath}/${c.id}`}
                selectedPath={this.props.selectedPath}
                onClick={this.props.onClick}
                level={this.props.level + 1}
            />)}
        </div>
    }
}
