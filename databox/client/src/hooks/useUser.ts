import {UserInfoResponse, useUser as baseUseUser} from '@alchemy/auth';

export function useUser() {
    return baseUseUser<UserInfoResponse>();
}
