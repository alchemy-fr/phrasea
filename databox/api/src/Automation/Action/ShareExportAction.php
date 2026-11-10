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
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Adds the assets to a share link and uploads, at the end of the run, an XLSX file listing them
 * with the public URLs (permalinks) of the chosen renditions, to an S3 bucket, an SFTP or an FTP server.
 *
 * The automation keeps one share per workspace (a share only holds assets of a single workspace),
 * filled run after run: the permalinks stay the same from one file to the next. A deleted share is
 * created again at the next run. The share only delivers the chosen renditions, among those of a
 * public policy. The file lists the assets of the run only.
 *
 * As the assets are marked as handled after each batch, a failed upload does not bring them back:
 * with the scope "new", they are only listed again after a reset of the cursor.
 */
#[AsTaggedItem(index: self::TYPE)]
final class ShareExportAction extends AbstractAutomationAction
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
    /** @var string array<string, string|null>, the share ID by workspace ID, null without any shareable rendition */
    private const string STATE_SHARES = 'shares';
    /** @var string array<string, string[]>, the IDs of the shared rendition definitions by workspace ID */
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
            'renditions' => $this->validateRenditions($config),
            'attributes' => self::validateAttributes($config),
            'locale' => self::validateLocale($config),
            'uploadWhenEmpty' => $uploadWhenEmpty,
            $protocol => $destination[$protocol],
        ];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $state = $batch->state;
        $config = $batch->config;
        $writer = $this->getWriter($state, $config);

        $shared = [];
        foreach ($batch->assets as $asset) {
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
     * The share of the automation for this workspace, created if missing,
     * or null when none of the chosen renditions can be shared there.
     */
    private function getShare(AutomationActionState $state, array $config, AutomationRunContext $context, Workspace $workspace): ?Share
    {
        $workspaceId = $workspace->getId();
        $known = $state->data[self::STATE_SHARES] ?? [];
        if (array_key_exists($workspaceId, $known)) {
            if (null === $known[$workspaceId]) {
                return null;
            }
            // The entity manager is cleared between the batches
            if (null !== $share = $this->em->find(Share::class, $known[$workspaceId])) {
                return $share;
            }
        }

        $definitions = $this->getSharedDefinitions($state, $config, $workspace);
        if (empty($definitions)) {
            $state->data[self::STATE_SHARES][$workspaceId] = null;

            return null;
        }

        $share = $this->automationShareRepository->findOneByAutomationAndWorkspace($context->automationId, $workspaceId)?->getShare();
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

        $state->data[self::STATE_SHARES][$workspaceId] = $share->getId();

        return $share;
    }

    /**
     * The definitions of the workspace with the chosen names a share may deliver (of a public policy),
     * reporting the others once per run.
     *
     * @return RenditionDefinition[]
     */
    private function getSharedDefinitions(AutomationActionState $state, array $config, Workspace $workspace): array
    {
        $workspaceId = $workspace->getId();
        if (!isset($state->data[self::STATE_DEFINITIONS][$workspaceId])) {
            /** @var RenditionDefinition[] $definitions */
            $definitions = $this->em->getRepository(RenditionDefinition::class)->findBy([
                'workspace' => $workspaceId,
                'name' => $config['renditions'],
            ]);

            $ids = [];
            $notPublic = [];
            foreach ($definitions as $definition) {
                if (true === $definition->getPolicy()?->isPublic()) {
                    $ids[] = $definition->getId();
                } else {
                    $notPublic[$definition->getName()] = true;
                }
            }

            foreach (array_keys($notPublic) as $name) {
                $state->addNotice(sprintf('Rendition "%s" of workspace "%s" cannot be shared: its policy is not public', $name, $workspace->getName()));
            }
            if (empty($ids)) {
                $state->addNotice(sprintf('None of the renditions can be shared in workspace "%s": its assets are listed without links', $workspace->getName()));
            }

            $state->data[self::STATE_DEFINITIONS][$workspaceId] = $ids;
        }

        return array_values(array_filter(array_map(
            fn (string $id): ?RenditionDefinition => $this->em->find(RenditionDefinition::class, $id),
            $state->data[self::STATE_DEFINITIONS][$workspaceId],
        )));
    }

    /**
     * @return string[]
     */
    private function getHeaders(array $config): array
    {
        $labels = [];
        foreach ($config['attributes'] as $slug) {
            $labels[$slug] = null;
        }
        if (!empty($labels)) {
            /** @var AttributeDefinition[] $definitions */
            $definitions = $this->em->getRepository(AttributeDefinition::class)->findBy(['slug' => array_keys($labels)]);
            foreach ($definitions as $definition) {
                $slug = (string) $definition->getSlug();
                if (null === $labels[$slug] && $this->isGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition)) {
                    $labels[$slug] = $definition->getName();
                }
            }
        }

        return [
            'ID',
            'Title',
            'Workspace',
            'Collection',
            'Created at',
            'Share URL',
            ...$config['renditions'],
            ...array_map(fn (string $slug): string => $labels[$slug] ?? $slug, array_keys($labels)),
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
            $asset->getWorkspace()?->getName(),
            null !== $collection && $this->isGranted(AbstractVoter::READ, $collection) ? $collection->getName() : null,
            $asset->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            null !== $share ? sprintf('%s/s/%s/%s', rtrim($this->databoxClientBaseUrl, '/'), $share->getId(), $share->getToken()) : null,
        ];

        $urls = null !== $share ? $this->renditionUrlGenerator->getRenditionUrls($share, $asset) : [];
        foreach ($config['renditions'] as $name) {
            $values[] = $urls[$name] ?? null;
        }

        if (!empty($config['attributes'])) {
            $locales = array_values(array_unique(array_filter([
                $config['locale'] ?? null,
                ...($asset->getWorkspace()?->getLocaleFallbacks() ?? []),
                AttributeInterface::NO_LOCALE,
            ])));
            $bySlug = self::indexBySlug($attributes);
            foreach ($config['attributes'] as $slug) {
                $values[] = $this->getAttributeValue($bySlug[$slug] ?? null, $locales);
            }
        }

        return $values;
    }

    /**
     * @return array<string, array<string, Attribute|Attribute[]>> the values by definition slug, then by locale
     */
    private static function indexBySlug(AttributeIndex $index): array
    {
        $bySlug = [];
        foreach ($index->getDefinitions() as $definitionIndex) {
            $slug = $definitionIndex->getDefinition()->getSlug();
            if (null !== $slug) {
                $bySlug[$slug] = $definitionIndex->getLocales();
            }
        }

        return $bySlug;
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
     * @return string[]
     */
    private function validateRenditions(array $config): array
    {
        $value = $config['renditions'] ?? [];
        if (!is_array($value)) {
            self::throwViolations(['renditions' => 'This value should be a list.']);
        }

        $names = [];
        foreach ($value as $name) {
            if (!is_string($name) || '' === trim($name)) {
                self::throwViolations(['renditions' => 'This value should be a list of rendition names.']);
            }
            $names[] = trim($name);
        }
        $names = array_values(array_unique($names));
        if (empty($names)) {
            self::throwViolations(['renditions' => 'Choose at least one rendition.']);
        }

        foreach ($names as $name) {
            /** @var RenditionDefinition[] $definitions */
            $definitions = $this->em->getRepository(RenditionDefinition::class)->findBy(['name' => $name]);
            $readable = array_filter($definitions, fn (RenditionDefinition $definition): bool => $this->isGranted(AbstractVoter::READ, $definition));
            if (empty($readable)) {
                self::throwViolations(['renditions' => sprintf('Rendition "%s" not found.', $name)]);
            }
        }

        return $names;
    }

    /**
     * @return string[] attribute definition slugs
     */
    private static function validateAttributes(array $config): array
    {
        $value = $config['attributes'] ?? [];
        if (!is_array($value)) {
            self::throwViolations(['attributes' => 'This value should be a list.']);
        }

        $slugs = [];
        foreach ($value as $slug) {
            if (!is_string($slug) || '' === trim($slug)) {
                self::throwViolations(['attributes' => 'This value should be a list of attribute slugs.']);
            }
            $slugs[] = trim($slug);
        }
        $slugs = array_values(array_unique($slugs));
        if (count($slugs) > self::MAX_ATTRIBUTES) {
            self::throwViolations(['attributes' => sprintf('There cannot be more than %d attributes.', self::MAX_ATTRIBUTES)]);
        }

        return $slugs;
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
