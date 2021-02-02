import {PureComponent, MouseEvent} from "react";
import {Collection} from "../../types";
import {getCollections} from "../../api/collection";
import {ReactComponent as ArrowDownImg} from '../../images/icons/arrow-down.svg';
import {ReactComponent as EditImg} from '../../images/icons/edit.svg';
import {ReactComponent as TrashImg} from '../../images/icons/trash.svg';
import Icon from "../ui/Icon";
import Button from "../ui/Button";
import apiClient from "../../api/api-client";
import EditCollection from "./Collection/EditCollection";

type State = {
    collections?: Collection[],
    expanded: boolean,
    editing: boolean,
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
        editing: false,
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

    edit = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({editing: true});
    }

    closeEdit = () => {
        this.setState({editing: false});
    }

    delete = (e: MouseEvent): void => {
        e.stopPropagation();
        if (window.confirm(`Delete? Really?`)) {
            apiClient.delete(`/collections/${this.props.id}`);
        }
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
        const {editing} = this.state;

        const selected = selectedPath === absolutePath;
        const currentInSelectedHierarchy = selectedPath && selectedPath.startsWith(absolutePath);

        return <div
            className={`collection-menu-wrapper`}
        >
            <div
                onClick={this.onClick}
                className={`menu-item ${this.state.expanded ? 'expanded' : ''} ${selected ? 'selected' : ''} ${currentInSelectedHierarchy ? 'current' : ''}`}

            >
                <div
                    className="i-title"
                    style={{
                        paddingLeft: (level * 10),
                    }}
                >
                    {title}
                </div>
                <div className="actions">
                    {capabilities.canEdit ? <Button
                        size={"sm"}
                        onClick={this.edit}
                    ><Icon
                        component={EditImg}/></Button> : ''}
                    {capabilities.canDelete ? <Button
                        size={"sm"}
                        onClick={this.delete}
                    ><Icon
                        component={TrashImg}
                    /></Button> : ''}
                </div>
                {children && children.length > 0 ? <div
                    className="expand"
                    onClick={this.onExpandClick}
                >
                    <Icon
                        variant={'xs'}
                        component={ArrowDownImg}
                    />
                </div> : ''}
            </div>
            {editing ? <EditCollection
                id={this.props.id}
                onClose={this.closeEdit}
            /> : ''}
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
                key={c.id}
                absolutePath={`${this.props.absolutePath}/${c.id}`}
                selectedPath={this.props.selectedPath}
                onClick={this.props.onClick}
                level={this.props.level + 1}
            />)}
        </div>
    }
}
