import {StrictDimensions, ZoomStepState} from "./index.ts";
import {Document, Page} from "react-pdf";
import LoadingPdfPage from "./LoadingPdfPage.tsx";
import {memo} from "react";

type Props = {
    file: string,
    onLoadSuccess: (pdf: any) => void,
    ratio: number | undefined,
    pdfDimensions: StrictDimensions,
    pageNumber: number,
    zoomStep: ZoomStepState,
    onRenderSuccess: () => void;
};

export default memo(function PdfView({
    onLoadSuccess,
    pdfDimensions,
    pageNumber,
    file,
    zoomStep,
    ratio,
    onRenderSuccess,
}: Props) {
    return <Document
        file={file}
        onLoadSuccess={onLoadSuccess}
        loading={<LoadingPdfPage/>}
    >
        {ratio ? (
            <>
                <Page
                    {...pdfDimensions}

                    pageNumber={pageNumber}
                    devicePixelRatio={
                        window.devicePixelRatio *
                        Math.min(
                            zoomStep.maxReached *
                            Math.max(
                                1,
                                Math.ceil(ratio / 3)
                            ),
                            8
                        )
                    }
                    loading={
                        <LoadingPdfPage/>
                    }
                    onRenderSuccess={onRenderSuccess}
                />
            </>
        ) : null}
    </Document>;
}, (prev, next) => {
    return prev.file === next.file
        && prev.pageNumber === next.pageNumber
        && prev.pdfDimensions.width === next.pdfDimensions.width
        && prev.pdfDimensions.height === next.pdfDimensions.height
        && prev.zoomStep.maxReached === next.zoomStep.maxReached
        ;
});
