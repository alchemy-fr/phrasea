import React, {PureComponent} from 'react';
import config from '../lib/config';
import apiClient from '../lib/apiClient';
import {PropTypes} from 'prop-types';
import FullPageLoader from "./FullPageLoader";
import {layouts} from "./layouts";
import ThemeEditorProxy from "./themes/ThemeEditorProxy";

class Publication extends PureComponent {
    static propTypes = {
        id: PropTypes.string.isRequired
    };

    state = {
        data: null,
    };

    static getDerivedStateFromProps(props, state) {
        if (props.id !== state.propsId) {
            return {
                propsId: props.id,
                data: null,
            }
        }

        return null;
    }

    componentDidMount() {
        const {id} = this.props;

        apiClient
            .get(`${config.getApiBaseUrl()}/publications/${id}`)
            .then((res) => {
                this.setState({data: res});
            });
    }

    render() {
        const {data} = this.state;

        if (null === data) {
            return <FullPageLoader/>;
        }

        return <div className={`publication`}>
            <ThemeEditorProxy
                data={data}
                render={data => {

                    if (!layouts[data.layout]) {
                        throw new Error(`Unsupported layout ${data.layout}`);
                    }

                    const Layout = layouts[data.layout];
                    return <Layout
                    data={data}
                />
                }}
            />
        </div>
    }
}

export default Publication;
