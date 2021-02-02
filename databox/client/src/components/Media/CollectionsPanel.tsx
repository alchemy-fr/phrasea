import React, {PureComponent} from "react";
import {Workspace} from "../../types";
import {getCollections, getWorkspaces} from "../../api/collection";
import {SelectionContext} from "./SelectionContext";
import WorkspaceMenuItem from "./WorkspaceMenuItem";

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

    render() {
        return <div className="collections">
            {this.renderResult()}
        </div>
    }

    renderResult() {
        const {workspaces} = this.state;

        return workspaces.map(c => <WorkspaceMenuItem
            {...c}
            key={c.id}
        />);
    }
}
