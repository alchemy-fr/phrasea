import React, {Component} from 'react';

export default class Container extends Component {
    render() {
        return (
            <div className="container">
                <div className="App">
                    <header>
                        <h1>Uploader.</h1>
                    </header>
                    <div>
                        {this.props.children}
                    </div>
                </div>
            </div>
        );
    }
}
