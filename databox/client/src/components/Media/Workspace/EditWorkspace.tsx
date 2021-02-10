import {PureComponent} from "react";
import {Workspace} from "../../../types";
import {FullPageLoader} from "@alchemy-fr/phraseanet-react-components";
import {RouteComponentProps} from "react-router-dom";
import {getWorkspace} from "../../../api/workspace";
import WorkspaceForm from "./WorkspaceForm";
import AclForm from "../../Acl/AclForm";
import {Link} from 'react-router-dom';
import TagList from "../Tag/TagList";

type Props = {
    id: string,
} & RouteComponentProps;

type State = {
    data?: Workspace;
};

export default class EditWorkspace extends PureComponent<Props, State> {
    state: State = {};

    load = async () => {
        const res: Workspace = await getWorkspace(this.props.id);

        this.setState({
            data: res,
        });
    }

    componentDidMount() {
        this.load();
    }

    render() {
        const {data} = this.state;

        if (!data) {
            return <FullPageLoader />;
        }

        return <div className={'container'}>
            <Link to={'/'}>Back</Link>
            <h2>Edit workspace <b>{data.name}</b></h2>
            <WorkspaceForm data={data} />
            <AclForm
                objectId={data.id}
                objectType={'workspace'}
            />
        </div>
    }
}
