
export const hydraDescriptionKey = 'hydra:description';

export function getApiResponseError(e: any): string | undefined {
    if (e.isAxiosError) {
        const status = e.response?.status ?? 0;
        const data = e.response.data;
        if (status === 422 && data.violations) {
            return data.violations.map((v: {
                message: string;
            }) => v.message).join("\n")
        }

        if (data['hydra:description']) {
            return `${data['hydra:title']}: ${data['hydra:description']}`;
        }

        return data['hydra:title'] ?? data['error_message'] ?? data['error'] ?? 'Error';
    }
}
