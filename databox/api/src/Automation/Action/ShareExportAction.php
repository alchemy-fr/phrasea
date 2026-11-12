<?php

declare(strict_types=1);

namespace App\Automation\Action;

use Alchemy\CoreBundle\Util\StringUtil;
use App\Attribute\AttributeInterface;
use App\Attribute\AttributeTypeRegistry;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Automation\Export\ExportPathTemplate;
use App\Automation\Export\RemoteDestinationConfig;
use App\Automation\Export\RemoteFilesystemFactory;
use App\Entity\Automation\AutomationShare;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use App\Repository\Automation\AutomationShareRepository;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\AttributeDefinitionVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Asset\Attribute\Index\AttributeIndex;
use App\Service\Share\ShareRenditionUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Adds the assets to a share link and uploads, at the end of the run, an XLSX file listing them
 * with the public URLs (permalinks) of the chosen renditions, to an S3 bucket, an SFTP or an FTP server.
 *
 * Works on a single workspace, the one the automation limits its results to (a share only holds
 * assets of a single workspace): the renditions and attributes are definitions of this workspace.
 * The automation keeps one share for it, filled run after run: the permalinks stay the same from
 * one file to the next. A deleted share is created again at the next run; another workspace chosen
 * for the automation gets a new share. The share only delivers the chosen renditions, among those
 * of a public policy. The file lists the assets of the run only.
 *
 * As the assets are marked as handled after each batch, a failed upload does not bring them back:
 * with the scope "new", they are only listed again after a reset of the cursor.
 */
#[AsTaggedItem(index: self::TYPE)]
final class ShareExportAction extends AbstractAutomationAction implements AutomationAwareActionInterface
{
    public const string TYPE = 'share_export';

    public const int MAX_ATTRIBUTES = 50;

    // The longest text a spreadsheet cell holds
    private const int MAX_CELL_LENGTH = 32767;
    private const int KEEP_ALIVE_EVERY = 50;
    // Length of the share name column
    private const int MAX_SHARE_NAME_LENGTH = 255;

    /** @var string the open XLSX writer */
    private const string STATE_WRITER = 'writer';
    /** @var string the path of the file being written */
    private const string STATE_FILE = 'file';
    /** @var string the number of assets written */
    private const string STATE_ROWS = 'rows';
    /** @var string string|null, the share ID, null without any shareable rendition */
    private const string STATE_SHARE = 'share';
    /** @var string string[], the IDs of the shared rendition definitions */
    private const string STATE_DEFINITIONS = 'definitions';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RemoteFilesystemFactory $filesystemFactory,
        private readonly AutomationShareRepository $automationShareRepository,
        private readonly ShareRenditionUrlGenerator $renditionUrlGenerator,
        private readonly AttributesResolver $attributesResolver,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly AssetNameResolver $assetNameResolver,
        private readonly string $databoxClientBaseUrl,
    ) {
    }

    public function getSecretFields(): array
    {
        return RemoteDestinationConfig::SECRET_FIELDS;
    }

    public function validateConfig(array $config): array
    {
        $destination = RemoteDestinationConfig::validate($config);
        $protocol = $destination['protocol'];

        $pathTemplate = $config['pathTemplate'] ?? null;
        if (null === $pathTemplate || '' === $pathTemplate) {
            $pathTemplate = ExportPathTemplate::REPORT_DEFAULT;
        }
        if (!is_string($pathTemplate)) {
            self::throwViolations(['pathTemplate' => 'This value should be a string.']);
        }
        $pathTemplate = ltrim(trim($pathTemplate), '/');
        if (null !== $error = ExportPathTemplate::validate($pathTemplate, ExportPathTemplate::REPORT_VARIABLES)) {
            self::throwViolations(['pathTemplate' => $error]);
        }

        $uploadWhenEmpty = $config['uploadWhenEmpty'] ?? false;
        if (!is_bool($uploadWhenEmpty)) {
            self::throwViolations(['uploadWhenEmpty' => 'This value should be a boolean.']);
        }

        return [
            'protocol' => $protocol,
            'pathTemplate' => $pathTemplate,
            'renditionIds' => self::validateRenditionIds($config),
            'attributeIds' => self::validateAttributeIds($config),
            'locale' => self::validateLocale($config),
            'uploadWhenEmpty' => $uploadWhenEmpty,
            $protocol => $destination[$protocol],
        ];
    }

    public function getAutomationViolations(array $config, SavedSearchAutomation $automation): array
    {
        $workspaceId = $automation->getWorkspaceId();
        if (null === $workspaceId) {
            return [self::WORKSPACE_KEY => 'Choose the workspace of the automation: this action only works on one workspace.'];
        }

        $violations = [];
        foreach ($config['renditionIds'] ?? [] as $id) {
            $definition = $this->em->find(RenditionDefinition::class, $id);
            if (null === $definition || $definition->getWorkspaceId() !== $workspaceId || !$this->isGranted(AbstractVoter::READ, $definition)) {
                $violations['renditionIds'] = sprintf('Rendition "%s" not found in the workspace.', $id);
                break;
            }
        }
        foreach ($config['attributeIds'] ?? [] as $id) {
            $definition = $this->em->find(AttributeDefinition::class, $id);
            if (null === $definition || $definition->getWorkspaceId() !== $workspaceId || !$this->isGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition)) {
                $violations['attributeIds'] = sprintf('Attribute "%s" not found in the workspace.', $id);
                break;
            }
        }

        return $violations;
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $state = $batch->state;
        $config = $batch->config;
        $workspaceId = $batch->context->workspaceId;
        if (null === $workspaceId) {
            foreach ($batch->assets as $asset) {
                $state->markFailed($asset, 'The automation has no workspace: edit it to choose one');
            }

            return;
        }

        $writer = $this->getWriter($state, $config);

        $shared = [];
        foreach ($batch->assets as $asset) {
            if ($asset->getWorkspaceId() !== $workspaceId) {
                $state->markSkipped($asset, 'The asset belongs to another workspace');
                continue;
            }
            if (!$this->isGranted(AssetVoter::READ, $asset) || !$this->isGranted(AssetVoter::SHARE, $asset)) {
                $state->markSkipped($asset, 'Not allowed to share the asset');
                continue;
            }

            $share = $this->getShare($state, $config, $batch->context, $asset->getWorkspace());
            $share?->addAsset($asset);
            $shared[] = [$asset, $share];
        }
        // The links are only written once the assets are in their share
        $this->em->flush();

        foreach ($shared as $i => [$asset, $share]) {
            if (0 === $i % self::KEEP_ALIVE_EVERY) {
                $batch->keepAlive();
            }

            $writer->addRow(new Row(array_map(self::createCell(...), $this->getRowValues($asset, $share, $config))));
            $state->data[self::STATE_ROWS] = ($state->data[self::STATE_ROWS] ?? 0) + 1;
            $state->markProcessed($asset);
        }
    }

    public function finish(AutomationActionState $state, array $config, AutomationRunContext $context): void
    {
        try {
            if (null === $context->workspaceId) {
                return;
            }

            $rows = $state->data[self::STATE_ROWS] ?? 0;
            if (0 === $rows && !($config['uploadWhenEmpty'] ?? false)) {
                return;
            }

            $this->getWriter($state, $config)->close();
            unset($state->data[self::STATE_WRITER]);

            $path = ExportPathTemplate::render($config['pathTemplate'], self::getPathVariables($context), ExportPathTemplate::REPORT_VARIABLES);
            $this->upload($config, $state->data[self::STATE_FILE], $path);
            $state->addNotice(sprintf('%d asset(s) exported to "%s"', $rows, $path));
        } finally {
            $this->cleanUp($state);
        }
    }

    private function getWriter(AutomationActionState $state, array $config): Writer
    {
        if (isset($state->data[self::STATE_WRITER])) {
            return $state->data[self::STATE_WRITER];
        }

        $file = tempnam(sys_get_temp_dir(), 'automation-share-export');
        if (false === $file) {
            throw new \RuntimeException('Unable to create the export file');
        }
        $state->data[self::STATE_FILE] = $file;

        $writer = new Writer();
        $writer->openToFile($file);
        $state->data[self::STATE_WRITER] = $writer;

        $writer->addRow(new Row(
            array_map(self::createCell(...), $this->getHeaders($config)),
            (new Style())->setFontBold(),
        ));

        return $writer;
    }

    private function cleanUp(AutomationActionState $state): void
    {
        $writer = $state->data[self::STATE_WRITER] ?? null;
        if ($writer instanceof Writer) {
            try {
                // Removes the temporary files of the writer
                $writer->close();
            } catch (\Throwable) {
            }
        }

        $file = $state->data[self::STATE_FILE] ?? null;
        if (null !== $file && file_exists($file)) {
            unlink($file);
        }

        $state->data = [];
    }

    private function upload(array $config, string $file, string $path): void
    {
        $filesystem = $this->filesystemFactory->create($config);
        $fd = fopen($file, 'r');
        if (false === $fd) {
            throw new \RuntimeException(sprintf('Unable to open file %s', $file));
        }

        try {
            $filesystem->writeStream($path, $fd);
        } catch (\Throwable $e) {
            throw new \RuntimeException(RemoteDestinationConfig::getDestinationError($e) ?? sprintf('The export could not be uploaded: %s', $e->getMessage()), 0, $e);
        } finally {
            if (is_resource($fd)) {
                fclose($fd);
            }
        }
    }

    /**
     * The share of the automation for its workspace, created if missing,
     * or null when none of the chosen renditions can be shared.
     */
    private function getShare(AutomationActionState $state, array $config, AutomationRunContext $context, Workspace $workspace): ?Share
    {
        if (array_key_exists(self::STATE_SHARE, $state->data)) {
            if (null === $state->data[self::STATE_SHARE]) {
                return null;
            }
            // The entity manager is cleared between the batches
            if (null !== $share = $this->em->find(Share::class, $state->data[self::STATE_SHARE])) {
                return $share;
            }
        }

        $definitions = $this->getSharedDefinitions($state, $config, $workspace);
        if (empty($definitions)) {
            $state->data[self::STATE_SHARE] = null;

            return null;
        }

        $share = $this->automationShareRepository->findOneByAutomationAndWorkspace($context->automationId, $workspace->getId())?->getShare();
        if (null === $share) {
            $share = new Share();
            $share->setName(mb_substr(sprintf('%s Automation %s', $context->savedSearchName ?? 'Saved search', $context->automationName), 0, self::MAX_SHARE_NAME_LENGTH));
            $share->setOwnerId($context->ownerId);
            $this->em->persist($share);
            $this->em->persist(new AutomationShare(
                $this->em->getReference(SavedSearchAutomation::class, $context->automationId),
                $workspace,
                $share,
            ));
        }

        // Follows the configuration of the action, which may have changed since the share was created
        foreach ($definitions as $definition) {
            $share->addRenditionDefinition($definition);
        }

        $state->data[self::STATE_SHARE] = $share->getId();

        return $share;
    }

    /**
     * The chosen definitions a share may deliver (of a public policy), reporting the others once per run.
     *
     * @return RenditionDefinition[]
     */
    private function getSharedDefinitions(AutomationActionState $state, array $config, Workspace $workspace): array
    {
        if (!isset($state->data[self::STATE_DEFINITIONS])) {
            $ids = [];
            foreach ($config['renditionIds'] as $id) {
                $definition = $this->em->find(RenditionDefinition::class, $id);
                if (null === $definition || $definition->getWorkspaceId() !== $workspace->getId()) {
                    $state->addNotice(sprintf('Rendition %s no longer exists', $id));
                } elseif (true === $definition->getPolicy()?->isPublic()) {
                    $ids[] = $definition->getId();
                } else {
                    $state->addNotice(sprintf('Rendition "%s" of workspace "%s" cannot be shared: its policy is not public', $definition->getName(), $workspace->getName()));
                }
            }

            if (empty($ids)) {
                $state->addNotice(sprintf('None of the renditions can be shared in workspace "%s": its assets are listed without links', $workspace->getName()));
            }

            $state->data[self::STATE_DEFINITIONS] = $ids;
        }

        return array_values(array_filter(array_map(
            fn (string $id): ?RenditionDefinition => $this->em->find(RenditionDefinition::class, $id),
            $state->data[self::STATE_DEFINITIONS],
        )));
    }

    /**
     * @return string[]
     */
    private function getHeaders(array $config): array
    {
        $renditions = array_map(
            fn (string $id): string => (string) ($this->em->find(RenditionDefinition::class, $id)?->getName() ?? $id),
            $config['renditionIds'],
        );
        $attributes = array_map(function (string $id): string {
            $definition = $this->em->find(AttributeDefinition::class, $id);

            return null !== $definition && $this->isGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition)
                ? (string) $definition->getName()
                : $id;
        }, $config['attributeIds']);

        return [
            'ID',
            'Title',
            'Collection',
            'Created at',
            'Share URL',
            ...$renditions,
            ...$attributes,
        ];
    }

    /**
     * @return array<int, string|null>
     */
    private function getRowValues(Asset $asset, ?Share $share, array $config): array
    {
        $attributes = $this->attributesResolver->resolveAssetAttributes($asset, true);
        $collection = $asset->getReferenceCollection();

        $values = [
            $asset->getId(),
            $this->assetNameResolver->resolveNameAsString($asset, $attributes),
            null !== $collection && $this->isGranted(AbstractVoter::READ, $collection) ? $collection->getName() : null,
            $asset->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            null !== $share ? sprintf('%s/s/%s/%s', rtrim($this->databoxClientBaseUrl, '/'), $share->getId(), $share->getToken()) : null,
        ];

        $urls = null !== $share ? $this->renditionUrlGenerator->getRenditionUrls($share, $asset) : [];
        foreach ($config['renditionIds'] as $id) {
            $values[] = $urls[$id] ?? null;
        }

        if (!empty($config['attributeIds'])) {
            $locales = array_values(array_unique(array_filter([
                $config['locale'] ?? null,
                ...($asset->getWorkspace()?->getLocaleFallbacks() ?? []),
                AttributeInterface::NO_LOCALE,
            ])));
            $byDefinitionId = self::indexByDefinitionId($attributes);
            foreach ($config['attributeIds'] as $id) {
                $values[] = $this->getAttributeValue($byDefinitionId[$id] ?? null, $locales);
            }
        }

        return $values;
    }

    /**
     * @return array<string, array<string, Attribute|Attribute[]>> the values by definition ID, then by locale
     */
    private static function indexByDefinitionId(AttributeIndex $index): array
    {
        $byId = [];
        foreach ($index->getDefinitions() as $definitionIndex) {
            $byId[$definitionIndex->getDefinition()->getId()] = $definitionIndex->getLocales();
        }

        return $byId;
    }

    /**
     * @param array<string, Attribute|Attribute[]>|null $byLocale
     * @param string[]                                  $locales  by preference
     */
    private function getAttributeValue(?array $byLocale, array $locales): ?string
    {
        if (empty($byLocale)) {
            return null;
        }

        $value = null;
        foreach ($locales as $locale) {
            if (isset($byLocale[$locale])) {
                $value = $byLocale[$locale];
                break;
            }
        }
        $value ??= reset($byLocale);

        $strings = [];
        foreach (is_array($value) ? $value : [$value] as $attribute) {
            $type = $this->attributeTypeRegistry->getStrictType((string) $attribute->getDefinition()->getType());
            $strings[] = $type->getStringValue($attribute->getValue(), $attribute->getLocale());
        }

        return implode("\n", $strings);
    }

    /**
     * Always a text: a value starting with "=" must not become a formula.
     */
    private static function createCell(?string $value): Cell
    {
        if (null === $value || '' === $value) {
            return new Cell\EmptyCell(null, null);
        }

        return new Cell\StringCell(mb_substr($value, 0, self::MAX_CELL_LENGTH), null);
    }

    /**
     * @return array<string, string>
     */
    private static function getPathVariables(AutomationRunContext $context): array
    {
        $now = new \DateTimeImmutable();
        $automation = StringUtil::slugify($context->automationName);
        $savedSearch = StringUtil::slugify((string) $context->savedSearchName);

        return [
            'automation' => '' !== $automation ? $automation : $context->automationId,
            'savedSearch' => '' !== $savedSearch ? $savedSearch : $context->savedSearchId,
            'date' => $now->format('Y-m-d'),
            'datetime' => $now->format('Ymd-His'),
            'runId' => $context->runId,
        ];
    }

    /**
     * Whether they exist in the workspace of the automation is checked by getAutomationViolations().
     *
     * @return string[] rendition definition IDs
     */
    private static function validateRenditionIds(array $config): array
    {
        $ids = self::getUuidList($config, 'renditionIds');
        if (empty($ids)) {
            self::throwViolations(['renditionIds' => 'Choose at least one rendition.']);
        }

        return $ids;
    }

    /**
     * @return string[] attribute definition IDs
     */
    private static function validateAttributeIds(array $config): array
    {
        $ids = self::getUuidList($config, 'attributeIds');
        if (count($ids) > self::MAX_ATTRIBUTES) {
            self::throwViolations(['attributeIds' => sprintf('There cannot be more than %d attributes.', self::MAX_ATTRIBUTES)]);
        }

        return $ids;
    }

    /**
     * @return string[]
     */
    private static function getUuidList(array $config, string $key): array
    {
        $ids = [];
        foreach (self::getIdList($config, $key) as $id) {
            if (!Uuid::isValid($id)) {
                self::throwViolations([$key => 'This value should be a list of IDs.']);
            }
            $ids[] = strtolower($id);
        }

        return array_values(array_unique($ids));
    }

    private static function validateLocale(array $config): ?string
    {
        $locale = $config['locale'] ?? null;
        if (null === $locale) {
            return null;
        }
        if (!is_string($locale)) {
            self::throwViolations(['locale' => 'This value should be a string.']);
        }
        $locale = trim($locale);
        if ('' === $locale) {
            return null;
        }
        if (!preg_match('/^[a-z]{2,3}([_-][a-z0-9]{2,8})*$/i', $locale)) {
            self::throwViolations(['locale' => 'This value is not a valid locale.']);
        }

        return $locale;
    }
}
