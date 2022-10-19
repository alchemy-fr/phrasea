declare module "*.svg" {
    const content: any;
    export default content;
    import {ReactElement, SVGProps} from "react";
    const ReactComponent: (props: SVGProps<SVGElement>) => ReactElement;
    export {ReactComponent}
}
