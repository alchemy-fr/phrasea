import request from "superagent";

class ApiClient {
    async get(uri, params = {}, options = {}) {
        const req = request
            .get(uri)
            .query(params);

        this.setOptions(req, options);

        const res = await req;
        return res.body;
    }

    async post(uri, data = {}, options = {}) {
        const req = request
            .post(uri)
            .send(data);

        this.setOptions(req, options);

        const res = await req;
        return res.body;
    }

    setOptions(req, options) {
        req.accept('json');

        if (options.headers) {
            Object.keys(options.headers).forEach(h => {
                req.set(h, options.headers[h]);
            });
        }

        if (options.withCredentials) {
            req.withCredentials();
        }
    }
}

const apiClient = new ApiClient();

export default apiClient;
