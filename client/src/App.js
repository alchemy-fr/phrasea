import React, {Component} from 'react';
import './scss/App.scss';
import Upload from "./components/Upload";

class App extends Component {
    render() {
        return (
            <div className="container">
                <div className="App">
                    <header>
                        <h1>Uploader.</h1>
                    </header>
                    <Upload/>
                </div>
            </div>
        );
    }
}

export default App;
