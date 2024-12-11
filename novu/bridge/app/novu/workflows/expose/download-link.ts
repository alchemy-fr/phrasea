import {workflow} from "@novu/framework";
import {z} from "zod";

export const exposeDownloadLink = workflow(
    "expose-download-link",
    async ({step, payload}) => {
        await step.email("Email", async () => {
            return {
                subject: `Your download link is ready!`,
                body: `You can download your file from the following link: ${payload.downloadUrl}`,
            };
        });
    },
    {
        payloadSchema: z.object({
            downloadUrl: z
                .string()
                .describe("The URL link to download the file"),
            locale: z
                .string()
                .describe("The user's locale"),
        })
    },
);
