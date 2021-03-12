import React, {MouseEvent, PureComponent} from "react";
import {Workspace} from "../../types";
import {getWorkspaces} from "../../api/collection";
import {SelectionContext} from "./SelectionContext";
import WorkspacesMenu from "./WorkspacesMenu";
import {CollectionMenuItemProps} from "./CollectionMenuItem";

type State = {
    workspaces: Workspace[];
};

export default class CollectionsPanel extends PureComponent<{}, State> {
    static contextType = SelectionContext;
    context: React.ContextType<typeof SelectionContext>;

    state: State = {
        workspaces: [],
    };

    componentDidMount() {
        this.load();
    }

    async load() {
        const data = await getWorkspaces();

        this.setState({workspaces: data});
    }

    onCollectionSelect = (collection: CollectionMenuItemProps, e: MouseEvent): void => {
        this.context.selectCollection(collection.absolutePath);
    }

    render() {
        return <div className="collections">
            {this.renderResult()}
        </div>
    }

    renderResult() {
        return <WorkspacesMenu
            workspaces={this.state.workspaces}
            selectedCollection={this.context.selectedCollection}
            selectedWorkspace={this.context.selectedWorkspace}
            onCollectionSelect={this.onCollectionSelect}
            onWorkspaceSelect={this.context.selectWorkspace}
        />
    }
}
