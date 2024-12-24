import {Button, Section, Text} from "@react-email/components";
import React from "react";
import DefaultEmail, {
    createEmailControlSchema,
    CreateEmailControlSchemaProps,
    styles
} from "@/app/novu/emails/DefaultEmail";
import {z, ZodDefault, ZodOptional, ZodString} from "zod";

type Props = {
    introText?: string;
    outroText?: string;
    linkUrl: string;
    linkText: string;
};

export default function CTAEmail({
    linkUrl,
    linkText,
    introText,
    outroText,
}: Props) {
    return (
        <DefaultEmail>
            <Section>
                {introText ? <Text>
                    {introText}
                </Text> : null}

                <Button style={styles.button} href={linkUrl}>{linkText}</Button>

                <Text>
                    <a href={linkUrl}>{linkUrl}</a>
                </Text>

                {outroText ? <Text>
                    {outroText}
                </Text> : null}
            </Section>
        </DefaultEmail>
    );
}

export type CreateCTAEEmailControlSchemaProps = {
    defaultIntroText?: string | undefined;
    defaultOutroText?: string | undefined;
} & CreateEmailControlSchemaProps;

export function createCTAEmailControlSchema({
    defaultIntroText,
    defaultOutroText,
    ...rest
}: CreateCTAEEmailControlSchemaProps) {
    return createEmailControlSchema({
        ...rest,
        shape: {
            ...(rest.shape ?? {}),
            introText: createOptionalOrNotString(defaultIntroText),
            outroText: createOptionalOrNotString(defaultOutroText),
        }
    });
}

function createOptionalOrNotString(defaultValue: string | undefined): ZodOptional<ZodString> | ZodDefault<ZodString> {
    const string = z.string();
    if (defaultValue) {
        return string.default(defaultValue);
    } else {
        return string.optional();
    }
}
