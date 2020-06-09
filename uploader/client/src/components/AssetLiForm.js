import React from 'react';
import {createStore, combineReducers} from 'redux';
import {reducer as formReducer} from 'redux-form';
import {Provider} from 'react-redux';
import Liform, {renderField, DefaultTheme} from '@alchemy-fr/liform-react';

const BaseForm = props => {
    const {schema, handleSubmit, theme, error, submitting, onCancel} = props;
    const disabled = submitting || error;

    return <form onSubmit={handleSubmit}>
        {renderField(schema, null, theme || DefaultTheme)}
        <div>
            {error && <div className="form-error">{error}</div>}
        </div>
        {onCancel ?
            <button
                className="btn btn-default"
                type="button"
                onClick={onCancel}
            >Cancel</button> : ''}
        <button
            className="btn btn-primary"
            type="submit"
            disabled={disabled}
        >Next
        </button>
    </form>;
};

const AssetLiForm = props => {
    const reducer = combineReducers({form: formReducer});
    const store = createStore(reducer);

    return <Provider store={store}>
        <Liform
            baseForm={BaseForm}
            schema={props.schema}
            onSubmit={props.onSubmit}
            onCancel={props.onCancel}
        />
    </Provider>;
};

export default AssetLiForm;
