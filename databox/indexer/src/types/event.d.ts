export type S3Record = {
    eventVersion: string;
    eventSource: string;
    awsRegion: string;
    eventTime: string;
    eventName: string;
    userIdentity: {
        principalId: string;
    };
    requestParameters: {
        principalId: string;
        region: string;
        sourceIPAddress: string;
    };
    responseElements: {
        'content-length': string;
        'x-amz-request-id': string;
        'x-minio-deployment-id': string;
        'x-minio-origin-endpoint': string;
    };
    s3: {
        s3SchemaVersion: string;
        configurationId: string;
        bucket: {
            name: string;
            ownerIdentity: {
                principalId: string;
            };
            arn: string;
        };
        object: {
            key: string;
            size: number;
            eTag: string;
            contentType: string;
            userMetadata: {
                'content-type': string;
            };
            sequencer: string;
        };
    };
    source: {
        host: string;
        port: string;
        userAgent: string;
    };
};

export type S3Event = {
    EventName: string;
    Key: string;
    Records: S3Record[];
};
