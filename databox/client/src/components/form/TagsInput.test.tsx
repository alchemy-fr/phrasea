import {describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen} from '@testing-library/react';
import type {ComponentProps} from 'react';
import {TagsInput} from './TagsInput';

function renderInput(props: Partial<ComponentProps<typeof TagsInput>> = {}) {
    const onChange = vi.fn();
    render(<TagsInput value={[]} onChange={onChange} {...props} />);

    return {onChange, input: screen.getByRole('textbox')};
}

const type = (input: HTMLElement, text: string) =>
    fireEvent.change(input, {target: {value: text}});

const paste = (input: HTMLElement, text: string) =>
    fireEvent.paste(input, {clipboardData: {getData: () => text}});

describe('TagsInput', () => {
    it('adds the typed value on a comma by default', () => {
        const {onChange, input} = renderInput({value: ['a']});
        type(input, 'b');
        fireEvent.keyDown(input, {key: ','});
        expect(onChange).toHaveBeenCalledWith(['a', 'b']);
    });

    it('splits a paste on commas and spaces by default', () => {
        const {onChange, input} = renderInput();
        paste(input, 'a, b c,,a');
        expect(onChange).toHaveBeenCalledWith(['a', 'b', 'c']);
    });

    it('keeps a comma in a value split on new lines', () => {
        const {onChange, input} = renderInput({splitOn: /\r?\n/});
        type(input, 'Paris');
        fireEvent.keyDown(input, {key: ','});
        expect(onChange).not.toHaveBeenCalled();
        type(input, 'Paris, France');
        fireEvent.keyDown(input, {key: 'Enter'});
        expect(onChange).toHaveBeenCalledWith(['Paris, France']);
    });

    it('splits a paste on new lines only', () => {
        const {onChange, input} = renderInput({splitOn: /\r?\n/});
        paste(input, 'a\nb, c');
        expect(onChange).toHaveBeenCalledWith(['a', 'b, c']);
    });

    it('removes the last value on Backspace in an empty field', () => {
        const {onChange, input} = renderInput({value: ['a', 'b']});
        fireEvent.keyDown(input, {key: 'Backspace'});
        expect(onChange).toHaveBeenCalledWith(['a']);
    });

    it('submits on Enter in an empty field', () => {
        const onSubmit = vi.fn();
        const {onChange, input} = renderInput({
            value: ['a'],
            initialText: 'b',
            onSubmit,
        });
        expect(input).toHaveProperty('value', 'b');
        fireEvent.keyDown(input, {key: 'Enter'});
        expect(onChange).toHaveBeenCalledWith(['a', 'b']);
        expect(onSubmit).not.toHaveBeenCalled();
        fireEvent.keyDown(input, {key: 'Enter'});
        expect(onSubmit).toHaveBeenCalledTimes(1);
    });
});
