import {workflow} from "@novu/framework";
import {z} from "zod";
import {
    render,
} from "@react-email/components";
import CTAEmail, {createCTAEmailControlSchema} from "@/app/novu/emails/CTAEmail";

export const exposeZippyDownloadLink = workflow(
    'expose-zippy-download-link',
    async ({step, payload}) => {
        await step.email("Email", async (controls) => {
            return {
                subject: controls.emailSubject,
                body: render(<CTAEmail
                    introText={`You can download your file from the following link:`}
                        linkUrl={payload.downloadUrl}
                        linkText={'Download'}
                    />
                ),
            };
        }, {
            controlSchema: createCTAEmailControlSchema({
                defaultEmailSubject: 'Your file is ready!',
                defaultIntroText: 'You can download your file from the following link:',
            })
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
