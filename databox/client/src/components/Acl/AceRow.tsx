import React, {ChangeEvent, PureComponent} from 'react';
import {Ace} from "../../types";
import {aclPermissions} from "./AclForm";
import {Button} from "@mui/material";

type Props = {
    onMaskChange: (userType: string, userId: string, mask: number) => void;
    onDelete: (userType: string, userId: string) => void;
} & Ace;

type State = {
    mask: number;
    propsMask?: number;
};

export default class AceRow extends PureComponent<Props, State> {
    state: State = {
        mask: 0,
    };

    static getDerivedStateFromProps(props: Props, state: State) {
        if (props.mask === state.propsMask) {
            return null;
        }

        return {
            mask: props.mask,
            propsMask: props.mask,
        }
    }

    onChangeMask = (e: ChangeEvent<HTMLInputElement>) => {
        const {checked} = e.target;
        const value = parseInt(e.target.value);

        this.setState(prevState => ({
            mask: prevState.mask + (checked ? value : -value),
        }), () => {
            this.props.onMaskChange(this.props.userType, this.props.userId, this.state.mask);
        });
    }

    delete = (): void => {
        this.props.onDelete(this.props.userType, this.props.userId);
    }

    render() {
        const {mask} = this.state;

        return <tr>
            <td>
                {`${this.props.userType} - ${this.props.userId}`}
            </td>
            {Object.keys(aclPermissions).map((k: string) => {
                return <td
                    key={k}
                >
                    <input
                        onChange={this.onChangeMask}
                        type="checkbox"
                        value={aclPermissions[k].toString()}
                        checked={(mask & aclPermissions[k]) === aclPermissions[k]}
                    />
                </td>
            })}
            <td>
                <Button
                    color={'error'}
                    onClick={this.delete}
                >
                    Delete
                </Button>
            </td>
        </tr>
    }
}
