<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use App\Entity\Core\TermsVersion;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final readonly class TermsPdfGenerator
{
    public function __construct(
        private Environment $twig,
        private LogoManager $logoManager,
    ) {
    }

    public function generatePdf(TermsVersion $terms): string
    {
        $workspace = $terms->getWorkspace();

        $html = $this->twig->render('terms/terms_pdf.html.twig', [
            'workspaceName' => $workspace->getName(),
            'logo' => $this->logoManager->resolveLogoForPdf($workspace),
            'version' => $terms->getVersion(),
            'text' => $terms->getText(),
            'date' => $terms->getCreatedAt(),
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setChroot(sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
