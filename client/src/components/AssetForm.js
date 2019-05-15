import React from 'react';
import { createStore, combineReducers } from 'redux';
import { reducer as formReducer } from 'redux-form';
import { Provider } from 'react-redux';
import Liform, {renderField, DefaultTheme} from 'liform-react';

const BaseForm = props => {
    const { schema, handleSubmit, theme, error, submitting } = props;
    const disabled = submitting || error;

    return (
        <form onSubmit={handleSubmit}>
            {renderField(schema, null, theme || DefaultTheme)}
            <div>
                {error && <strong>{error}</strong>}
            </div>
            <button
                className="btn btn-primary"
                type="submit"
                disabled={disabled}
            >Next</button>
        </form>);
};

const AssetForm = props => {
    const reducer = combineReducers({ form: formReducer });
    const store = createStore(reducer);

    return (
        <Provider store={store}>
            <Liform
                baseForm={BaseForm}
                schema={props.schema}
                onSubmit={props.onSubmit}
            />
        </Provider>
    )
};

export default AssetForm;
