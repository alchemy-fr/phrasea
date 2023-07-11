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
        this.setState({workspaces: await getWorkspaces()});
    }

    render() {
        console.log('this.state.workspaces', this.state.workspaces);
        return <>
            {this.state.workspaces.map(w => <WorkspaceMenuItem
                {...w}
                key={w.id}
            />)}
        </>
    }
}
