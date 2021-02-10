import {MouseEvent, PureComponent} from "react";
import {Asset} from "../../types";
import {Badge} from "react-bootstrap";
import Button from "../ui/Button";
import Icon from "../ui/Icon";
import {ReactComponent as EditImg} from "../../images/icons/edit.svg";
import {ReactComponent as TrashImg} from "../../images/icons/trash.svg";
import apiClient from "../../api/api-client";
import EditAsset from "./Asset/EditAsset";

type Props = {
    selected?: boolean;
    onClick?: (id: string, e: MouseEvent) => void;
};

const privacyIndices = [
    'Secret',
    'Private in workspace',
    'Public in workspace',
    'Private',
    'Public for users',
    'Public',
];

type State = {
    editing: boolean;
}

export default class AssetItem extends PureComponent<Props & Asset, State> {
    state: State = {
        editing: false,
    };

    onClick = (e: MouseEvent): void => {
        const {onClick} = this.props;

        onClick && onClick(this.props.id, e);
    }

    edit = (e: MouseEvent): void => {
        e.stopPropagation();
        this.setState({editing: true});
    }

    closeEdit = () => {
        this.setState({editing: false});
    }

    delete = (e: MouseEvent): void => {
        e.stopPropagation();
        if (window.confirm(`Delete? Really?`)) {
            apiClient.delete(`/assets/${this.props.id}`);
        }
    }

    render() {
        const {
            title,
            description,
            tags,
            privacy,
            selected,
            collections,
            capabilities,
        } = this.props;

        const privacyLabel = privacyIndices[privacy];

        return <div
            onClick={this.onClick}
            className={`asset-item ${selected ? 'selected' : ''}`}
        >
            <div className="a-thumb">
                <div className="actions">
                    {capabilities.canEdit ? <Button
                        size={"sm"}
                        onClick={this.edit}
                    ><Icon
                        component={EditImg}/></Button> : ''}
                    {capabilities.canDelete ? <Button
                        size={"sm"}
                        onClick={this.delete}
                    ><Icon
                        component={TrashImg}
                    /></Button> : ''}
                </div>
                <img
                    src="https://user-images.githubusercontent.com/194400/49531010-48dad180-f8b1-11e8-8d89-1e61320e1d82.png"
                    alt="Placeholder"/>
            </div>
            <div className="a-footer">
                <div className="a-title">
                    {title}
                </div>
                <div>
                    {collections.map(c => <div
                        key={c.id}
                    >{c.title}</div>)}
                </div>
                <div className="a-desc">
                    {description ? <p>{description}</p> : ''}

                    {tags.map(t => <Badge
                        variant={'success'}
                        key={t.id}
                    >{t.name}</Badge>)}
                    <Badge
                        variant={'info'}
                    >{privacyLabel}</Badge>
                </div>
                {this.state.editing ? <EditAsset
                    id={this.props.id}
                    onClose={this.closeEdit}
                /> : ''}
            </div>
        </div>
    }
}
