import React, {Component} from 'react';
import AssetForm from "./AssetForm";
import PropTypes from "prop-types";

export default class FormPreview extends Component {
    constructor(props) {
        super(props);
        this.state = {
            error: null,
        }
    }

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
                schema = <AssetForm
                    onSubmit={(data) => alert('Form submitted with values: '+JSON.stringify(data, true, 2))}
                    schema={schemaConfig}
                />;
            } catch (e) {
                error = e;
                schema = false;
            }
        }

        return null === error ? schema : <div className="text-danger">
            {error.toString()}
        </div>;
    }
}

FormPreview.propTypes = {
    schema: PropTypes.string.isRequired,
};
