import {describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen} from '@testing-library/react';
import type {User} from '@/types/api';
import {MentionSuggestionList} from './MentionSuggestions';

const named: User = {
    id: 'u1',
    username: 'john.doe@a-very-long-domain.example.com',
    firstName: 'John',
    lastName: 'Doe',
};
const anonymous: User = {id: 'u2', username: 'jane'};

function setup(active = 0) {
    const onPick = vi.fn();
    const onHover = vi.fn();
    const utils = render(
        <MentionSuggestionList
            suggestions={[named, anonymous]}
            active={active}
            anchor={{left: 10, top: 20}}
            onPick={onPick}
            onHover={onHover}
        />
    );

    return {...utils, onPick, onHover};
}

describe('MentionSuggestionList', () => {
    it('shows the name, then the username underneath', () => {
        setup();
        const [first] = screen.getAllByRole('option');
        const lines = first.querySelectorAll('span');
        expect(lines).toHaveLength(2);
        expect(lines[0].textContent).toBe('John Doe');
        expect(lines[0].getAttribute('title')).toBe('John Doe');
        expect(lines[1].textContent).toBe(
            '@john.doe@a-very-long-domain.example.com'
        );
        expect(lines[1].getAttribute('title')).toBe(
            '@john.doe@a-very-long-domain.example.com'
        );
    });

    it('shows the username alone when the user has no name', () => {
        setup();
        const second = screen.getAllByRole('option')[1];
        const lines = second.querySelectorAll('span');
        expect(lines).toHaveLength(1);
        expect(lines[0].textContent).toBe('@jane');
        expect(lines[0].getAttribute('title')).toBe('@jane');
    });

    it('picks and highlights the hovered user', () => {
        const {onPick, onHover, rerender} = setup();
        const options = screen.getAllByRole('option');
        expect(options[0].getAttribute('aria-selected')).toBe('true');
        expect(options[1].getAttribute('aria-selected')).toBe('false');

        fireEvent.mouseEnter(options[1]);
        expect(onHover).toHaveBeenCalledWith(1);
        fireEvent.mouseDown(options[1]);
        expect(onPick).toHaveBeenCalledWith(anonymous);

        rerender(
            <MentionSuggestionList
                suggestions={[named, anonymous]}
                active={1}
                anchor={{left: 10, top: 20}}
                onPick={onPick}
                onHover={onHover}
            />
        );
        expect(
            screen.getAllByRole('option')[1].getAttribute('aria-selected')
        ).toBe('true');
    });

    it('is wide enough to read the usernames', () => {
        setup();
        expect(screen.getByRole('listbox').style.width).toBe('320px');
    });
});
