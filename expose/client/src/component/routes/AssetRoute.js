import React, {PureComponent} from 'react';
import Publication from "../Publication";

class AssetRoute extends PureComponent {
    render() {
        return <Publication
            id={this.props.match.params.publication}
            assetId={this.props.match.params.asset}
            authenticated={this.props.authenticated}
        />
    }
}

export default AssetRoute;
