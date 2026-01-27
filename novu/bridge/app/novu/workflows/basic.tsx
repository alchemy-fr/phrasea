import {workflow} from "@novu/framework";
import {z} from "zod";
import {Button, render, Section, Text} from "@react-email/components";
import DefaultEmail, {styles} from "@/app/novu/emails/DefaultEmail";
import React from "react";

export const basic = workflow(
    "basic",
    async ({step, payload: {subject, content, url}}) => {
        await step.inApp("In-App Step", async () => {
            return {
                subject,
                body: content,
                redirect: url ? {
                    url,
                } : undefined,
            };
        });

        await step.email("send-email", async () => {
            return {
                subject,
                body: await render(<DefaultEmail>
                    <Section>
                        <Text>
                            {content}
                        </Text>
                        {url ? <Button style={styles.button}>View</Button> : null}
                    </Section>
                </DefaultEmail>
                ),
            };
        });
    },
    {
        payloadSchema: z.object({
            url: z
                .string()
                .optional()
                .describe("The resource URL"),
            subject: z
                .string()
                .describe("The message subject"),
            content: z
                .string()
                .describe("The message content"),
        })
    },
);
