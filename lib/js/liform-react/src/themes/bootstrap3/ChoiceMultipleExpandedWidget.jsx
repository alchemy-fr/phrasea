import React from 'react';
import classNames from 'classnames';
import {Field} from 'redux-form';

const zipObject = (props, values) =>
    props.reduce(
        (prev, prop, i) => Object.assign(prev, {[prop]: values[i]}),
        {}
    );

const changeValue = (checked, item, onChange, currentValue = []) => {
    if (checked) {
        if (currentValue.indexOf(checked) === -1) {
            return onChange([...currentValue, item]);
        }
    } else {
        return onChange(currentValue.filter(it => it !== item));
    }
    return onChange(currentValue);
};

const renderChoice = field => {
    const className = classNames([
        'form-group',
        {'has-error': field.meta.touched && field.meta.error},
    ]);
    const s = field.schema;
    const options = s.items?.enum || s.enum;
    const optionNames = s.items?.enum_titles || s.enum_titles || options;

    const selectOptions = zipObject(options, optionNames);
    return (
        <div className={className}>
            <label className="control-label" htmlFor={'field-' + field.name}>
                {field.label}
            </label>
            {Object.entries(selectOptions).map(([value, name]) => (
                <div className="checkbox" key={value}>
                    <label>
                        <input
                            type="checkbox"
                            value={value}
                            checked={field.input.value.includes(value)}
                            onChange={e =>
                                changeValue(
                                    e.target.checked,
                                    value,
                                    field.input.onChange,
                                    field.input.value
                                )
                            }
                        />
                        {' '}
                        {name}
                    </label>
                </div>
            ))}

            {field.meta.touched && field.meta.error && (
                <span className="help-block">{field.meta.error}</span>
            )}
            {field.description && (
                <span className="help-block">{field.description}</span>
            )}
        </div>
    );
};

const ChoiceMultipleExpandedWidget = props => {
    return (
        <Field
            component={renderChoice}
            label={props.label}
            name={props.fieldName}
            required={props.required}
            id={'field-' + props.fieldName}
            placeholder={props.schema.default}
            description={props.schema.description}
            schema={props.schema}
            multiple={true}
        />
    );
};

export default ChoiceMultipleExpandedWidget;
