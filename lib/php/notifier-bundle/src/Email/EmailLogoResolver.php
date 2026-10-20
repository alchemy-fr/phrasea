<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Email;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Asks the logo providers in priority order: the first valid source wins,
 * otherwise the Phrasea logo is used.
 */
final readonly class EmailLogoResolver
{
    private const int LOGO_MAX_WIDTH = 200;
    private const int LOGO_MAX_HEIGHT = 48;
    private const int DEFAULT_LOGO_SIZE = 40;
    private const string DATA_URI_REGEX = '#^data:(image/(?:png|jpe?g|gif|svg\+xml));base64,([a-z0-9+/=\s]+)$#i';

    /**
     * @param iterable<EmailLogoProviderInterface> $logoProviders
     */
    public function __construct(
        #[AutowireIterator(EmailLogoProviderInterface::TAG)]
        private iterable $logoProviders = [],
        private string $defaultLogoPath = __DIR__.'/../../assets/email/phrasea-logo.png',
    ) {
    }

    public function resolve(): EmailLogo
    {
        foreach ($this->logoProviders as $provider) {
            $src = trim($provider->getLogoSrc() ?? '');

            if (preg_match(self::DATA_URI_REGEX, $src, $matches)) {
                $content = base64_decode(preg_replace('#\s+#', '', $matches[2]), true);
                if (false === $content || '' === $content) {
                    continue;
                }

                [$width, $height] = $this->fit($content);

                return new EmailLogo(
                    custom: true,
                    content: $content,
                    mimeType: strtolower($matches[1]),
                    width: $width,
                    height: $height,
                );
            }

            if (preg_match('#^https?://#i', $src)) {
                return new EmailLogo(custom: true, remoteUrl: $src);
            }
        }

        return new EmailLogo(
            custom: false,
            content: (string) file_get_contents($this->defaultLogoPath),
            mimeType: 'image/png',
            width: self::DEFAULT_LOGO_SIZE,
            height: self::DEFAULT_LOGO_SIZE,
        );
    }

    /**
     * Outlook ignores CSS sizes on images: explicit dimensions, scaled down to
     * the header box, are given whenever the image can be measured (not SVG).
     *
     * @return array{int|null, int|null}
     */
    private function fit(string $content): array
    {
        $size = @getimagesizefromstring($content);
        if (false === $size || $size[0] <= 0 || $size[1] <= 0) {
            return [null, null];
        }

        [$width, $height] = $size;
        $ratio = min(1, self::LOGO_MAX_WIDTH / $width, self::LOGO_MAX_HEIGHT / $height);

        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }
}
