import React, {PureComponent} from 'react';
import AceRow from "./AceRow";
import {Ace} from "../../types";
import {getAces, putAce} from "../../api/acl";

type Props = {
    objectType: string;
    objectId: string;
};

type State = {
    aces?: Ace[];
};

export const aclPermissions: { [key: string]: number } = {
    VIEW: 1,
    CREATE: 2,
    EDIT: 4,
    DELETE: 8,
    UNDELETE: 16,
    OPERATOR: 32,
    MASTER: 64,
    OWNER: 128,
}


export default class AclForm extends PureComponent<Props, State> {
    state: State = {};

    async loadPermissions() {
        const aces = await getAces(this.props.objectType, this.props.objectId);
        this.setState({aces});
    }

    componentDidMount() {
        this.loadPermissions();
    }

    render() {
        return <div>
            {this.renderAces()}
        </div>
    }

    onMaskChange = async (userType: string, userId: string, mask: number) => {
        await putAce(userType, userId, this.props.objectType, this.props.objectId, mask);
    }

    renderAces() {
        const {aces} = this.state;

        if (!aces) {
            return 'Loading permissions...';
        }

        return <table>
            <thead>
            <tr>
                <th>User/Group</th>
                {Object.keys(aclPermissions).map(k => {
                    return <th
                        key={k}
                    >
                        {k}
                    </th>
                })}
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
                {aces.map((ace) => <AceRow
                    onMaskChange={this.onMaskChange}
                    {...ace}
                    key={ace.id}
                />)}
            </tbody>
        </table>
    }
}
