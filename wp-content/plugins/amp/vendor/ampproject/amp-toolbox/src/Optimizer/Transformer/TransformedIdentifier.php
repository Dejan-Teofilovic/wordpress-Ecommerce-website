<?php

namespace AmpProject\Optimizer\Transformer;

use AmpProject\Dom\Document;
use AmpProject\Optimizer\Configuration\TransformedIdentifierConfiguration;
use AmpProject\Optimizer\ErrorCollection;
use AmpProject\Optimizer\Transformer;
use AmpProject\Optimizer\TransformerConfiguration;
use AmpProject\Validator\Spec\CssRuleset\AmpTransformed;
use AmpProject\Validator\Spec\SpecRule;

/**
 * Transformer applying the transformed identifier transformations to the HTML input.
 *
 * This is ported from the NodeJS optimizer while verifying against the Go version.
 *
 * NodeJS:
 * @version 2ca65a94b77130c91ac11fcc32c94b93cbd2b7a0
 * @link    https://github.com/ampproject/amp-toolbox/blob/2ca65a94b77130c91ac11fcc32c94b93cbd2b7a0/packages/optimizer/lib/transformers/AddTransformedFlag.js
 *
 * Go:
 * @version b26a35142e0ed1458158435b252a0fcd659f93c4
 * @link    https://github.com/ampproject/amppackager/blob/b26a35142e0ed1458158435b252a0fcd659f93c4/transformer/transformers/transformedidentifier.go
 *
 * @package ampproject/amp-toolbox
 */
final class TransformedIdentifier implements Transformer
{
    /**
     * Attribute name of the "transformed" identifier.
     *
     * @var string
     */
    const TRANSFORMED_ATTRIBUTE = 'transformed';

    /**
     * Origin to use for the "transformed" identifier.
     *
     * @var string
     */
    const TRANSFORMED_ORIGIN = 'self';

    /**
     * Configuration store to use.
     *
     * @var TransformerConfiguration
     */
    private $configuration;

    /**
     * Instantiate a TransformedIdentifier object.
     *
     * @param TransformerConfiguration $configuration Configuration store to use.
     */
    public function __construct(TransformerConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Apply transformations to the provided DOM document.
     *
     * @param Document        $document DOM document to apply the transformations to.
     * @param ErrorCollection $errors   Collection of errors that are collected during transformation.
     * @return void
     */
    public function transform(Document $document, ErrorCollection $errors)
    {
        $document->html->setAttribute(self::TRANSFORMED_ATTRIBUTE, $this->getOrigin());

        // Ensure that the document uses the larges CSS byte limit for transformed documents,
        // as it would probably be set to the non-transformed limit at this point.
        $enforced_max_byte_count = $this->configuration->get(
            TransformedIdentifierConfiguration::ENFORCED_CSS_MAX_BYTE_COUNT
        );
        if ($enforced_max_byte_count !== false) {
            $document->enforceCssMaxByteCount($enforced_max_byte_count);
        }
    }

    /**
     * Get the origin that transformed the AMP document.
     *
     * @return string Origin of the transformation.
     */
    private function getOrigin()
    {
        $version = $this->configuration->get(TransformedIdentifierConfiguration::VERSION);
        $origin  = self::TRANSFORMED_ORIGIN;

        if ($version > 0) {
            $origin .= ";v={$version}";
        }

        return $origin;
    }
}
