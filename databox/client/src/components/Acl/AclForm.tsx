import React, {PureComponent} from 'react';
import AceRow from "./AceRow";
import {Ace, Group, User} from "../../types";
import {getAces, putAce} from "../../api/acl";
import {getGroups, getUsers} from "../../api/user";
import UserSelect from "../User/UserSelect";
import GroupSelect from "../User/GroupSelect";

type Props = {
    objectType: string;
    objectId: string;
};

type State = {
    aces?: Ace[];
    users?: User[];
    groups?: Group[];
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

    async load() {
        const aces = await getAces(this.props.objectType, this.props.objectId);
        const users = await getUsers();
        const groups = await getGroups();
        this.setState({
            aces,
            users,
            groups,
        });
    }

    componentDidMount() {
        this.load();
    }

    render() {
        return <div>
            <div>

            </div>
            {this.renderAces()}
        </div>
    }

    onMaskChange = async (userType: string, userId: string, mask: number) => {
        await putAce(userType, userId, this.props.objectType, this.props.objectId, mask);
    }

    onSelectUser = (a: User) => {
        console.log('u', a);
    }

    onSelectGroup = (a: Group) => {
        console.log('g', a);
    }

    renderAces() {
        const {aces} = this.state;

        if (!aces) {
            return 'Loading permissions...';
        }

        return <div>
            <div>
                <UserSelect
                    onSelect={this.onSelectUser}
                />
                <GroupSelect
                    onSelect={this.onSelectGroup}
                />
            </div>
            <table>
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
        </div>
    }
}
