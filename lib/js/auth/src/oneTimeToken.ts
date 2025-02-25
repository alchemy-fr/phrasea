import type {HttpClient} from '@alchemy/api';

export async function getOneTimeToken(client: HttpClient): Promise<string> {
    return (await client.post(`/ott`, {})).data.token;
}
