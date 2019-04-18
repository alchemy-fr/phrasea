import React, {Component} from 'react';
import config from '../../store/config';

export default class DevSettings extends Component {
    constructor(props) {
        super(props);

        this.state = {
            uploadBaseUrl: config.getUploadBaseURL(),
            saved: false,
        }
    }

    onChange(e) {
        this.setState({
            uploadBaseUrl: e.target.value,
            saved: false,
        });
    }

    save() {
        config.setUploadBaseURL(this.state.uploadBaseUrl);

        this.setState({
            saved: true,
        }, () => {
            setTimeout(() => {
                this.setState({saved: false});
            }, 3000);
        });
    }

    render() {
        const {uploadBaseUrl, saved} = this.state;

        return (
            <div className="container">
                <h1>DEV Settings</h1>

                <div className="form-group">
                    <label>Upload Base URL</label>
                    <input
                        type="text"
                        className="form-control"
                        value={uploadBaseUrl}
                        onChange={(e) => this.onChange(e)}
                    />
                </div>

                <div className="form-group">
                    <button
                        onClick={() => this.save()}
                        className="btn btn-primary">
                        Save
                    </button>
                    {saved ? (<span>
                        {' '}
                            <span className="badge badge-success">saved!</span>
                        </span>
                    ) : ''}
                </div>
            </div>
        );
    }
}
