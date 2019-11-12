import React, {PureComponent} from 'react';
import FullPageLoader from "../FullPageLoader";
import apiClient from "../../lib/apiClient";
import config from "../../lib/config";

class PublicationIndex extends PureComponent {
    state = {
        data: null,
    };

    componentDidMount() {
        apiClient
            .get(`${config.getApiBaseUrl()}/publications`)
            .then((res) => {
                this.setState({data: res});
            });
    }

    render() {
        const {data} = this.state;

        if (null === data) {
            return <FullPageLoader/>
        }

        return <div className="container">
            <h1>Publications index</h1>
            <ul className={'publication-index'}>
                {this.renderPublications()}
            </ul>
        </div>
    }

    renderPublications() {
        const {data} = this.state;

        return data.map(p => <li
            key={p.id}
        >
            <a href={`/${p.slug || p.id}`}>
                {p.cover ? <img src={p.cover.thumbUrl} alt=""/> : ''}
                {p.title}
            </a>
        </li>)
    }
}

export default PublicationIndex;
