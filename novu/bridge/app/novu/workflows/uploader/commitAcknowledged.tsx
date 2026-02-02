import {workflow} from "@novu/framework";
import {z} from "zod";
import {render, Section, Text} from "@react-email/components";
import DefaultEmail, {styles} from "@/app/novu/emails/DefaultEmail";
import React from "react";

export const uploaderCommitAcknowledged = workflow(
    "uploader-commit-acknowledged",
    async ({step, payload}) => {
        await step.inApp("In-App Step", async () => {
            // @ts-expect-error unknown issue
            const n = parseInt(payload.assetCount);
            const plural = n > 1 ? 's' : '';

            return {
                // @ts-expect-error unknown issue
                subject: `You have **${payload.assetCount}** new asset${plural}!`,
                // @ts-expect-error unknown issue
                body: `The **${payload.assetCount}** asset${plural} you uploaded were correctly handled.`,
            };
        });

        await step.email("send-email", async () => {
            // @ts-expect-error unknown issue
            const n = parseInt(payload.assetCount);
            const plural = n > 1 ? 's' : '';

            return {
                subject: `You have new asset !`,
                body: await render(
                    <DefaultEmail>
                        <Section>
                            <Text style={styles.text}>
                                {/* @ts-expect-error unknown issue */}
                                The <strong>{payload.assetCount}</strong> asset
                                {plural} you uploaded were correctly handled.
                            </Text>
                        </Section>
                    </DefaultEmail>
                ),
            };
        });
    },
    {
        payloadSchema: z.object({
            assetCount: z
                .string()
                .describe("The number of assets in the commit"),
        })
    },
);
