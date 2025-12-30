import React, {Component} from 'react';
// import PropTypes from "prop-types";
import AssetLiForm from './Upload/AssetLiForm';

export default class FormPreview extends Component {
    // static propTypes = {
    //     schema: PropTypes.string.isRequired,
    // };

    state = {
        error: null,
    };

    componentWillReceiveProps(nextProps, nextContext) {
        if (nextProps.schema !== this.props.schema) {
            this.setState({error: null});
        }
    }

    componentDidCatch(error, info) {
        this.setState({error});
    }

    render() {
        let {error} = this.state;
        let schema;

        if (null === error) {
            try {
                const schemaConfig = JSON.parse(this.props.schema);
                schema = (
                    <AssetLiForm
                        onSubmit={data =>
                            alert(
                                'Form submitted with values: ' +
                                    JSON.stringify(data, true, 2)
                            )
                        }
                        schema={schemaConfig}
                    />
                );
            } catch (e) {
                error = e;
                schema = false;
            }
        }

        return null === error ? (
            schema
        ) : (
            <div className="text-danger">{error.toString()}</div>
        );
    }
}
