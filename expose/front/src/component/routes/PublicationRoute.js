import React, {PureComponent} from 'react';
import Publication from "../Publication";

class PublicationRoute extends PureComponent {
    render() {
        return <Publication
            id={this.props.match.params.id || this.props.match.params.publication}
        />
    }
}

export default PublicationRoute;
