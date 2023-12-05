import React, {PureComponent} from 'react';
import Loader from 'react-loader-spinner';

export default class FullPageLoader extends PureComponent {
    render() {
        return (
            <div className="full-page-loader">
                <Loader
                    type="MutatingDots"
                    color="#000"
                    height={100}
                    width={100}
                />
            </div>
        );
    }
}
