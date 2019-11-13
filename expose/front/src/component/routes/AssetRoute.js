import React, {PureComponent} from 'react';
import Publication from "../Publication";

class AssetRoute extends PureComponent {
    render() {
        return <Publication
            id={this.props.match.params.publication}
            assetSlug={this.props.match.params.asset}
        />
    }
}

export default AssetRoute;
