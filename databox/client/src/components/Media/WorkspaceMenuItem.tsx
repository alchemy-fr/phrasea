import React, {PureComponent, MouseEvent} from "react";
import {Workspace} from "../../types";
import {Link} from 'react-router-dom'
import {ReactComponent as ArrowDownImg} from '../../images/icons/arrow-down.svg';
import {ReactComponent as EditImg} from '../../images/icons/edit.svg';
import Icon from "../ui/Icon";
import Button from "../ui/Button";
import {SelectionContext} from "./SelectionContext";
import CollectionMenuItem, {CollectionMenuItemProps} from "./CollectionMenuItem";

type State = {
    expanded: boolean,
}

export type WorkspaceMenuItemProps = {
} & Workspace;

export default class WorkspaceMenuItem extends PureComponent<WorkspaceMenuItemProps, State> {
    static contextType = SelectionContext;
    context: React.ContextType<typeof SelectionContext>;

    state: State = {
        expanded: true,
    };

    expandWorkspace = async (force = false): Promise<void> => {
        this.setState((prevState: State) => ({
            expanded: !prevState.expanded || force,
        }));
    }

    onClick = (e: MouseEvent): void => {
        this.context.selectWorkspace(this.props.id);
        this.expandWorkspace(true);
    }

    onExpandClick = (e: MouseEvent) => {
        e.stopPropagation();
        this.expandWorkspace();
    }

    render() {
        const {
            id,
            name,
            collections,
            capabilities,
        } = this.props;

        const selected = this.context.selectedWorkspace === id;
        const currentInSelectedHierarchy = !!this.context.selectedCollection;

        return <div
            className={`workspace-menu-wrapper`}
        >
            <div
                onClick={this.onClick}
                className={`menu-item ${this.state.expanded ? 'expanded' : ''} ${selected ? 'selected' : ''} ${currentInSelectedHierarchy ? 'current' : ''}`}

            >
                <div
                    className="i-title"
                >
                    {name}
                </div>
                <div className="actions">
                    {capabilities.canEdit ? <Link
                        to={`/workspaces/${this.props.id}/edit`}
                    ><Icon
                        component={EditImg}/></Link> : ''}
                </div>
                {collections && collections.length > 0 ? <div
                    className="expand"
                    onClick={this.onExpandClick}
                >
                    <Icon
                        variant={'xs'}
                        component={ArrowDownImg}
                    />
                </div> : ''}
            </div>
            {this.renderChildren()}
        </div>
    }

    onCollectionSelect = (collection: CollectionMenuItemProps, e: MouseEvent): void => {
        this.context.selectCollection(collection.absolutePath);
    }

    renderChildren() {
        const {collections} = this.props;
        const {expanded} = this.state;
        if (!expanded || !collections) {
            return '';
        }

        return <div>
            {collections.map(c => <CollectionMenuItem
                {...c}
                key={c.id}
                absolutePath={c.id}
                selectedPath={this.context.selectedCollection}
                onClick={this.onCollectionSelect}
                level={0}
            />)}
        </div>
    }
}
