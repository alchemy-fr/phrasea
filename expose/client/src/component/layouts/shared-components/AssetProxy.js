import React, {PureComponent} from 'react';

class AssetProxy extends PureComponent {
    render() {
        const {type} = this.props;

        switch (true) {
            case 'application/pdf' === type:
                return <PDF {...asset} />
            case type.startsWith('image/'):
                return <PDF {...asset} />
            case type.startsWith('video/'):
                return <PDF {...asset} />
        }
    }
}
