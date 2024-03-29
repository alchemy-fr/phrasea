import React from 'react';
import {combineReducers} from 'redux';
import {reducer as formReducer} from 'redux-form';
import {Provider} from 'react-redux';
import Liform, {renderField, DefaultTheme} from '@alchemy/liform-react';
import {configureStore} from '@reduxjs/toolkit';

const BaseForm = props => {
    const {schema, handleSubmit, theme, error, submitting, onCancel} = props;
    const disabled = submitting || error;

    return (
        <form onSubmit={handleSubmit}>
            {renderField(schema, null, theme || DefaultTheme)}
            <div>{error && <div className="form-error">{error}</div>}</div>
            {onCancel ? (
                <button
                    className="btn btn-default"
                    type="button"
                    onClick={onCancel}
                >
                    Cancel
                </button>
            ) : (
                ''
            )}
            <button
                className="btn btn-primary"
                type="submit"
                disabled={disabled}
            >
                Next
            </button>
        </form>
    );
};

const AssetLiForm = props => {
    const reducer = combineReducers({form: formReducer});
    const store = configureStore({reducer});

    const initialValues = {};

    const properties = props.schema.properties;
    if (properties) {
        Object.keys(properties).forEach(k => {
            if (properties[k].defaultValue) {
                initialValues[k] = properties[k].defaultValue;
                delete properties[k].defaultValue;
            }
        });
    }

    return (
        <Provider store={store}>
            <Liform
                baseForm={BaseForm}
                initialValues={initialValues}
                schema={props.schema}
                onSubmit={props.onSubmit}
                onCancel={props.onCancel}
            />
        </Provider>
    );
};

export default AssetLiForm;
