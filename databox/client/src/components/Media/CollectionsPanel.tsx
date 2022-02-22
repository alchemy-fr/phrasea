import React, {PureComponent} from "react";
import {Workspace} from "../../types";
import {getWorkspaces} from "../../api/collection";
import WorkspaceMenuItem from "./WorkspaceMenuItem";

type State = {
    workspaces: Workspace[];
};

export default class CollectionsPanel extends PureComponent<{}, State> {
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
        return <ul className="collections">
            {this.state.workspaces.map(w => <WorkspaceMenuItem
                {...w}
                key={w.id}
            />)}
        </ul>
    }
}
