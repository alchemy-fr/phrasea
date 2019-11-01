import request from "superagent";

class ApiClient {
    accessToken;

    async get(uri, params = {}) {
        const req = await request
            .get(uri)
            .query(params)
            .accept('json');

        if (this.accessToken) {

        }

        const res = await req;
        return res.body;
    }

    async post(uri, data = {}) {
        const req = request
            .post(uri)
            .send(data)
            .accept('json');

        if (this.accessToken) {

        }

        const res = await req;
        return res.body;
    }
}

const apiClient = new ApiClient();

export default apiClient;
