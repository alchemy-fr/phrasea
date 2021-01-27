import {MouseEvent, PureComponent} from "react";
import {Asset} from "../../types";
import {Badge} from "react-bootstrap";

type Props = {
    selected?: boolean;
    onClick?: (id: string, e: MouseEvent) => void;
}

export default class AssetItem extends PureComponent<Props & Asset> {
    onClick = (e: MouseEvent): void => {
        const {onClick} = this.props;

        onClick && onClick(this.props.id, e);
    }

    render() {
        const {
            title,
            description,
            tags,
            public: isPublic,
            selected,
            collections,
        } = this.props;

        return <div
            onClick={this.onClick}

            className={`asset-item ${selected ? 'selected' : ''}`}>
            <div className="a-thumb">
                <img
                    src="https://user-images.githubusercontent.com/194400/49531010-48dad180-f8b1-11e8-8d89-1e61320e1d82.png"
                    alt="Placeholder"/>
            </div>
            <div className="a-footer">
                <div className="a-title">
                    {title}
                </div>
                <div>
                    {collections.map(c => <div>{c.title}</div>)}
                </div>
                <div className="a-desc">
                    {description ? <p>{description}</p> : ''}

                    {tags.map(t => <Badge
                        variant={'info'}
                        key={t.id}
                    >{t.name}</Badge>)}

                    {isPublic ? <Badge
                        key={'public'}
                        variant={'success'}
                    >Public</Badge> : <Badge
                        key={'private'}
                        variant={'danger'}
                    >Private</Badge>}
                </div>
            </div>
        </div>
    }
}
