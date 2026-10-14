<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use App\Service\Workspace\TermsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Only the current text of the terms: the PDF is a file of the workspace, not configuration.
 */
#[AsTaggedItem(priority: 40)]
final class TermsSection extends AbstractTemplateSection
{
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        private readonly TermsManager $termsManager,
    ) {
        parent::__construct($em, $logger);
    }

    public static function getKey(): string
    {
        return 'Terms';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        $terms = $this->termsManager->getCurrentTerms($workspace);
        if (null === $terms) {
            return [];
        }

        return [
            'text' => $terms->getText(),
            'translations' => $terms->getFieldTranslations(TermsVersion::TR_FIELD_TEXT) ?: [],
        ];
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        if (!isset($data['text'])) {
            return;
        }

        $this->logger->info('Updating Terms');
        $this->termsManager->updateTerms($context->workspace, $data['text'], $data['translations'] ?? []);
    }
}
