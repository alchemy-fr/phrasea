import {Container, Grid} from "@mui/material";
import Service from "./Service";
import ClientApp from "./ClientApp.tsx";
import config from "./config.ts";

type Props = {};

export default function Root({}: Props) {
    const {
        DATABOX_API_URL,
        EXPOSE_API_URL,
        UPLOADER_API_URL,
        NOTIFY_API_URL,
        KEYCLOAK_URL,
        DATABOX_CLIENT_URL,
        EXPOSE_CLIENT_URL,
        UPLOADER_CLIENT_URL,
    } = config.env;

    console.log('config.env', config.env);

    return <Container>
        <Grid
            sx={{
                pt: 2,
            }}
            container
            spacing={2}
        >
            <Service
                mainUrl={KEYCLOAK_URL}
                title={`Identity Manager`}
                description={`Keycloak IAM`}
                logo={'/src/images/keycloak.png'}
            />
            {DATABOX_API_URL && <ClientApp
                apiUrl={DATABOX_API_URL}
                clientUrl={DATABOX_CLIENT_URL}
                title={`Databox`}
                description={`Your DAM`}
                logo={'/src/images/databox.png'}
            />}
            {EXPOSE_API_URL && <ClientApp
                apiUrl={EXPOSE_API_URL}
                clientUrl={EXPOSE_CLIENT_URL}
                title={`Expose`}
                description={`Share Publications`}
                logo={'/src/images/expose.png'}
            />}
            {UPLOADER_API_URL && <ClientApp
                apiUrl={UPLOADER_API_URL}
                clientUrl={UPLOADER_CLIENT_URL}
                title={`Uploader`}
                description={`Standalone Asset deposit`}
                logo={'/src/images/uploader.png'}
            />}
            {NOTIFY_API_URL && <Service
                mainUrl={`${NOTIFY_API_URL}/admin`}
                title={`Notify`}
                description={`Mail Sender`}
                logo={'/src/images/notify.png'}
            />}
        </Grid>
    </Container>
}
