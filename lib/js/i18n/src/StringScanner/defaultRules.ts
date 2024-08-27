import {JsxAttributeOrPropertyNameRule, FunctionNameRule, JsxElementNameRule, OneOfNodeTypeRule, VariableNameRule} from "./rules";
import {Rule} from "./types";
import {SyntaxKind} from "ts-morph";

export const defaultRules: Rule[] = [
    new OneOfNodeTypeRule([
        SyntaxKind.TypeReference,
        SyntaxKind.IndexedAccessType,
        SyntaxKind.BinaryExpression,
        SyntaxKind.ElementAccessExpression,
    ]),
    new JsxElementNameRule([
        /^Trans$/,
    ]),
    new VariableNameRule([
        /^(data|d)$/,
        /^(aria|class|anchor)/,
        /(Id|Sx)$/,
        /Class(es|Name)?$/,
        /^class/,
    ]),
    new FunctionNameRule([
        /^t$/,
        /^has/,
        /^(watch|register)$/,
        /^NumberFormat$/,
        /^useState$/,
        /^(debug|append|log)/,
    ]),
    new JsxAttributeOrPropertyNameRule([
        /^(aria|class|anchor)/,
        /Class(Name)?$/,
        /(Id|Sx|Url)$/,
        /^(min|max)(Width|Height)$/,
        /^(field|placement|sx|key|color|role|loadingPosition|height|variant|width|style|modifiers|transform|direction|orientation|alignItems|valueLabelDisplay|component|mouseEvent|id|position|origin|padding|transition|background)$/,
        /content-type/i,
        /accept/i,
        /url/i,
    ]),
];
