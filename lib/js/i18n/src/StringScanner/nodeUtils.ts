import {JsxAttributeName, Node, PropertyName} from "ts-morph";

export function resolveName(node: Node): string {
    if (Node.isIdentifier(node)) {
        return node.getText();
    }
    if (Node.isStringLiteral(node)) {
        return node.getLiteralText();
    }
    if (Node.isNoSubstitutionTemplateLiteral(node)) {
        return node.getLiteralText();
    }
    if (Node.isComputedPropertyName(node)) {
        return resolveName(node.getChildAtIndex(1));
    }

    return node.getText();
}

export function isAllowedAttribute(node: JsxAttributeName | PropertyName): boolean {
    return isAllowed([
        /^(aria|class|anchor)/,
        /Class(Name)?$/,
        /(Id|Sx|Url)$/,
        /^(min|max)(Width|Height)$/,
        /^(field|placement|sx|key|color|role|loadingPosition|height|variant|width|style|modifiers|transform|direction|orientation|alignItems|valueLabelDisplay|component|mouseEvent|id|position|origin|padding|transition|background)$/,
        /content-type/i,
        /accept/i,
        /url/i,
    ], resolveName(node));
}

export function isAllowedText(txt: string): boolean {
    return isAllowed([
        /^h[1-6]$/,
        /^(primary|secondary|default|warning|error|info|success)$/,
        /^(small|large)$/,
        /^(string|object|number)$/,
        /^(submit)$/,
        /^(contained|outlined)$/,
        /^(lg|md|sm|xs)$/,
        /^(nowrap|inherit)$/,
        /^(item|key|row|column|left|right|top|bottom|text)$/,
        /^onMouse(Down|Up|Click|Move)$/,
        /^onKey(Down|Up|Press)$/,
        /^key(down|up|press)$/,
        /^mouse(down|up|press|move)$/,
        /^anchor(Position|El)$/,
        /^[()\[\]\-|/+•#%:]$/,
        /^\//,
        /^debug/,
        /^\d+(px|r?em|%)$/,
        /^#(\d{3}|\d{6})$/,
    ], txt);
}

export function isAllowedFunctionName(name: string): boolean {
    return isAllowed([
        /^t$/,
        /^has/,
        /^(watch|register)$/,
        /^NumberFormat$/,
        /^useState$/,
        /^(debug|append|log)/,
    ], name.replace(/^(.+\.)+/, ''));
}

export function isAllowedVariableName(name: string): boolean {
    return isAllowed([
        /^(data|d)$/,
        /^(aria|class|anchor)/,
        /(Id|Sx)$/,
        /Class(es|Name)?$/,
        /^class/,
    ], name);
}

function isAllowed(blacklist: RegExp[], str: string): boolean {
    if (!str) {
        return false;
    }

    for (const reg of blacklist) {
        if (str.match(reg)) {
            return false;
        }
    }

    return true;
}
