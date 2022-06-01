import React, {PureComponent} from 'react';
import AceRow from "./AceRow";
import {Ace, Group, User} from "../../types";
import {deleteAce, getAces, putAce} from "../../api/acl";
import {getGroups, getUsers} from "../../api/user";
import UserSelect from "../User/UserSelect";
import GroupSelect from "../User/GroupSelect";
import {Grid} from "@mui/material";
import FormRow from "../Form/FormRow";

type Props = {
    objectType: "collection" | "asset" | "workspace";
    objectId: string;
};

type State = {
    aces?: Ace[];
    users?: User[];
    groups?: Group[];
};

export const aclPermissions: { [key: string]: number } = {
    VIEW: 1,
    SHARE: 256,
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
        const {aces} = this.state;

        return <div>
            <Grid container spacing={2}>
                <Grid item md={6}>
                    <FormRow>
                        <GroupSelect
                            placeholder={`Select group`}
                            clearOnSelect={true}
                            onChange={(option) => {
                                option && this.onSelectGroup(option.value);
                            }}
                            disabledValues={aces ? aces.filter(ace => ace.userType === 'group').map(ace => ace.userId) : undefined}
                        />
                    </FormRow>
                </Grid>
                <Grid item md={6}>
                    <FormRow>
                        <UserSelect
                            placeholder={`Select user`}
                            clearOnSelect={true}
                            onChange={(option) => {
                                option && this.onSelectUser(option.value)
                            }}
                            disabledValues={aces ? aces.filter(ace => ace.userType === 'user').map(ace => ace.userId) : undefined}
                        />
                    </FormRow>
                </Grid>
            </Grid>
            {this.renderAces()}
        </div>
    }

    onMaskChange = async (userType: string, userId: string, mask: number) => {
        await putAce(userType, userId, this.props.objectType, this.props.objectId, mask);
    }

    onDelete = async (userType: string, userId: string) => {
        this.setState(prevState => {
            return {
                aces: prevState.aces!.filter((ace: Ace) => !(ace.userType === userType && ace.userId === userId)),
            };
        });

        await deleteAce(userType, userId, this.props.objectType, this.props.objectId);
    }

    onSelectUser = (id: string) => {
        this.addEntry({
            type: 'user',
            id,
        }, 1);
    }

    onSelectGroup = (id: string) => {
        this.addEntry({
            type: 'group',
            id,
        }, 1);
    }

    addEntry(entry: { id: string, type: string }, mask: number) {
        putAce(entry.type, entry.id, this.props.objectType, this.props.objectId, mask);

        this.setState(prevState => {
            const aces = [...(prevState.aces || [])];

            aces.push({
                mask: mask,
                userId: entry.id,
                userType: entry.type,
            } as Ace);

            return {
                aces,
            };
        });
    }

    renderAces() {
        const {aces} = this.state;

        if (!aces) {
            return 'Loading permissions...';
        }

        return <table className={'table acl-table'}>
            <thead>
            <tr>
                <th>User/Group</th>
                {Object.keys(aclPermissions).map(k => {
                    return <th
                        key={k}
                        className={'perm'}
                    >
                        <span>{k}</span>
                    </th>
                })}
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            {aces.map((ace) => <AceRow
                onMaskChange={this.onMaskChange}
                onDelete={this.onDelete}
                {...ace}
                key={ace.id || `${ace.userId}::${ace.userType}`}
            />)}
            </tbody>
        </table>
    }
}
