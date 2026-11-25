import {beforeEach, describe, expect, it, vi} from 'vitest';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {useUploaderUser} from '@/features/user/useUploaderUser';
import type {UploaderUser} from '@/types/api';
import {RequirePermission} from './RequirePermission';

vi.mock('@/features/user/useUploaderUser', () => ({
    useUploaderUser: vi.fn(),
}));

function mockPermissions(permissions: Partial<UploaderUser['permissions']>) {
    vi.mocked(useUploaderUser).mockReturnValue({
        data: {
            user_id: 'u1',
            username: 'user',
            permissions: {
                form_schema: false,
                target_data: false,
                upload_link: false,
                target: false,
                ...permissions,
            },
        },
        error: null,
        isPending: false,
    } as unknown as ReturnType<typeof useUploaderUser>);
}

function renderGate(
    permission: Parameters<typeof RequirePermission>[0]['permission']
) {
    return render(
        <I18nextProvider i18n={createI18n('en')}>
            <RequirePermission permission={permission}>
                <p>granted</p>
            </RequirePermission>
        </I18nextProvider>
    );
}

describe('RequirePermission', () => {
    beforeEach(() => {
        vi.mocked(useUploaderUser).mockReset();
    });

    it('opens the screen with the permission', () => {
        mockPermissions({upload_link: true});
        renderGate('upload_link');

        expect(screen.getByText('granted')).toBeTruthy();
    });

    it('refuses the screen without the permission', () => {
        mockPermissions({form_schema: true});
        renderGate('upload_link');

        expect(screen.queryByText('granted')).toBeNull();
        expect(screen.getByTestId('forbidden')).toBeTruthy();
    });

    it('opens the screen with any of several permissions', () => {
        mockPermissions({target_data: true});
        renderGate(['form_schema', 'target_data']);

        expect(screen.getByText('granted')).toBeTruthy();
    });

    it('refuses the screen with none of several permissions', () => {
        mockPermissions({upload_link: true});
        renderGate(['form_schema', 'target_data']);

        expect(screen.queryByText('granted')).toBeNull();
        expect(screen.getByTestId('forbidden')).toBeTruthy();
    });
});
