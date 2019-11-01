import React, {PureComponent} from 'react';
import Publication from "../Publication";

class PublicationRoute extends PureComponent {
    render() {
        return <Publication
            id={this.props.match.params.id}
        />
    }
}

export default PublicationRoute;
