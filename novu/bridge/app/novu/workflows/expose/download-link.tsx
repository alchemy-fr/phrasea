import {workflow} from "@novu/framework";
import {z} from "zod";
import {render} from "@react-email/components";
import CTAEmail from "@/app/novu/emails/CTAEmail";

export const exposeDownloadLink = workflow(
    "expose-download-link",
    async ({step, payload}) => {
        await step.email("Email", async () => {
            return {
                subject: `Your download link is ready!`,
                body: await render(
                    <CTAEmail
                        introText={`You can download your file from the following link:`}
                        // @ts-expect-error unknown issue
                        linkUrl={payload.downloadUrl}
                        linkText={'Download'}
                    />
                ),
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
