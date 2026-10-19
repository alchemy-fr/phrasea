import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {ModalProvider} from '@/components/modals/ModalProvider';
import {DefinitionManager} from './DefinitionManager';

vi.mock('next/navigation', () => ({
    usePathname: () => '/workspaces/ws/manage/attributes',
}));

const {toast} = vi.hoisted(() => ({
    toast: {success: vi.fn(), error: vi.fn()},
}));

vi.mock('sonner', () => ({toast}));

type Item = {id: string; name: string};

const items: Item[] = [
    {id: 'a', name: 'Alpha'},
    {id: 'b', name: 'Bravo'},
];

function renderManager(props: {
    onDuplicate?: (item: Item) => Promise<Item>;
    onChanged?: () => void;
}) {
    return render(
        <I18nextProvider i18n={createI18n('en')}>
            <ModalProvider>
                <DefinitionManager<Item>
                    items={items}
                    renderItem={i => <span>{i.name}</span>}
                    renderForm={i => (
                        <div data-testid="form">{i?.name ?? 'new'}</div>
                    )}
                    onDelete={async () => undefined}
                    onDuplicate={props.onDuplicate}
                    onChanged={props.onChanged ?? (() => undefined)}
                />
            </ModalProvider>
        </I18nextProvider>
    );
}

describe('DefinitionManager', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('has no duplicate action without onDuplicate', () => {
        renderManager({});

        expect(screen.getAllByTestId('definition-item')).toHaveLength(2);
        expect(screen.queryAllByTestId('definition-duplicate')).toHaveLength(0);
    });

    it('duplicates an item, then selects the copy', async () => {
        const copy: Item = {id: 'c', name: 'Alpha (copy)'};
        const onDuplicate = vi.fn(async () => copy);
        const onChanged = vi.fn();

        renderManager({onDuplicate, onChanged});

        const buttons = screen.getAllByTestId('definition-duplicate');
        expect(buttons).toHaveLength(2);
        expect(buttons[0].getAttribute('aria-label')).toBe('Duplicate');

        fireEvent.click(buttons[0]);

        await waitFor(() => expect(onChanged).toHaveBeenCalledTimes(1));
        expect(onDuplicate).toHaveBeenCalledWith(items[0]);
        // Not in the (not yet refreshed) list, the copy is still edited
        await waitFor(() =>
            expect(screen.getByTestId('form').textContent).toBe('Alpha (copy)')
        );
        expect(toast.success).toHaveBeenCalledWith('Copy created');
    });

    it('reports a failed duplication', async () => {
        const onDuplicate = vi.fn(async () => {
            throw new Error('Forbidden');
        });
        const onChanged = vi.fn();

        renderManager({onDuplicate, onChanged});

        fireEvent.click(screen.getAllByTestId('definition-duplicate')[1]);

        await waitFor(() =>
            expect(toast.error).toHaveBeenCalledWith('Forbidden')
        );
        expect(onDuplicate).toHaveBeenCalledWith(items[1]);
        expect(onChanged).not.toHaveBeenCalled();
        expect(screen.queryByTestId('form')).toBeNull();
    });
});
