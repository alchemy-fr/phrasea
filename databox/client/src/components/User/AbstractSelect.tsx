import {PureComponent} from "react";
import AsyncSelect from "react-select/async";
import {ValueType} from "react-select";

export type UserOrGroupOption = {
    label: string;
    value: string;
};

type Props<T> = {
    onSelect?: (item: T) => void;
    clearOnSelect?: boolean;
    disabledValues?: string[];
};

type State = {
    inputValue: string;
    value: ValueType<UserOrGroupOption, false>;
}

export default abstract class AbstractSelect<T> extends PureComponent<Props<T>, State> {
    state: State = {
        inputValue: '',
        value: null,
    };

    abstract optionToData(option: UserOrGroupOption): T;

    abstract dataToOption(data: T): UserOrGroupOption;

    abstract load(): Promise<T[]>;

    abstract getType(): string;

    handleInputChange = (newValue: string) => {
        const inputValue = newValue.replace(/\W/g, '');
        this.setState({inputValue});
        return inputValue;
    };

    handleLoad = async (inputValue: string): Promise<UserOrGroupOption[]> => {
        const data = await this.load();

        const values = data.map(this.dataToOption)
            .filter(i => i.label.toLowerCase().includes(inputValue.toLowerCase()));

        const {disabledValues} = this.props;
        if (disabledValues) {
            console.log('disabledValues', disabledValues);
            return values.map(i => ({
                ...i,
                isDisabled: disabledValues.includes(i.value),
            }))
        }

        return values;
    }

    onChange = (data: ValueType<UserOrGroupOption, false>): void => {
        this.setState({value: this.props.clearOnSelect ? null : data}, () => {
            if (data) {
                const {onSelect} = this.props;
                onSelect && onSelect(this.optionToData(data));
            }
        });
    }

    getValue() {
        return this.state.value ? this.state.value.value : null;
    }

    render() {
        const {disabledValues} = this.props;

        const isOptionDisabled = disabledValues ? (o: UserOrGroupOption) => disabledValues!.includes(o.value) : undefined;

        return <AsyncSelect
            cacheOptions
            defaultOptions
            placeholder={`Select a ${this.getType()}`}
            onChange={this.onChange}
            loadOptions={this.handleLoad}
            value={this.state.value}
            isOptionDisabled={isOptionDisabled}
        />
    }
}
