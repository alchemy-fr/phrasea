import {PureComponent} from "react";
import {Collection} from "../../types";
import {getCollections} from "../../api/collection";
import {ReactComponent as ArrowDownImg} from '../../images/icons/arrow-down.svg';
import Icon from "../ui/Icon";

type State = {
    collections?: Collection[],
    expanded: boolean,
}

type CollectionMenuItemProps = {
    level: number;
}

export default class CollectionMenuItem extends PureComponent<Collection & CollectionMenuItemProps, State> {
    state: State = {
        expanded: false,
    };

    expandCollection = async (): Promise<void> => {
        this.setState((prevState: State) => {
            return {
                expanded: !prevState.expanded,
            };
        }, async (): Promise<void> => {
            if (this.state.expanded) {

                const data = await getCollections({
                    parent: this.props.id,
                });
                this.setState({collections: data});
            }
        });
    }

    render() {
        return <div
            className={'collection-menu-wrapper'}
        >
            <div
                onClick={this.expandCollection}
                className={`collection-menu-item ${this.state.expanded ? 'expanded' : ''}`}
                style={{
                    paddingLeft: (this.props.level * 10),
                }}
            >
                <div
                    className="c-title"
                >
                    {this.props.title}
                </div>
                <div className="expand">
                    <Icon component={ArrowDownImg}/>
                </div>
            </div>
            {this.renderChildren()}
        </div>
    }

    renderChildren() {
        const {collections, expanded} = this.state;
        if (!expanded || !collections) {
            return '';
        }

        return <div className="sub-colls">
            {collections.map(c => <CollectionMenuItem
                {...c}
                level={this.props.level + 1}
            />)}
        </div>
    }
}
