<?php

namespace AmpProject;

use AmpProject\Dom\Document;
use AmpProject\Dom\Element;
use DOMNode;

/**
 * Helper functionality to deal with AMP dev-mode.
 *
 * @link    https://github.com/ampproject/amphtml/issues/20974
 *
 * @package ampproject/amp-toolbox
 */
final class DevMode
{
    /**
     * Attribute name for AMP dev mode.
     *
     * @var string
     */
    const DEV_MODE_ATTRIBUTE = 'data-ampdevmode';

    /**
     * Check whether the provided document is in dev mode.
     *
     * @param Document $document Document for which to check whether dev mode is active.
     * @return bool Whether the document is in dev mode.
     */
    public static function isActiveForDocument(Document $document)
    {
        return $document->documentElement->hasAttribute(self::DEV_MODE_ATTRIBUTE);
    }

    /**
     * Check whether a node is exempt from validation during dev mode.
     *
     * @param DOMNode $node Node to check.
     * @return bool Whether the node should be exempt during dev mode.
     */
    public static function hasExemptionForNode(DOMNode $node)
    {
        if (! $node instanceof Element) {
            return false;
        }

        $document = self::getDocument($node);

        if ($node === $document->documentElement) {
            return $document->hasInitialAmpDevMode();
        }

        return $node->hasAttribute(self::DEV_MODE_ATTRIBUTE);
    }

    /**
     * Check whether a certain node should be exempt from validation.
     *
     * @param DOMNode $node Node to check.
     * @return bool Whether the node should be exempt from validation.
     */
    public static function isExemptFromValidation(DOMNode $node)
    {
        $document = self::getDocument($node);
        return self::isActiveForDocument($document) && self::hasExemptionForNode($node);
    }

    /**
     * Get the document from the specified node.
     *
     * @param DOMNode $node The Node from which the document should be retrieved.
     * @return Document
     */
    private static function getDocument(DOMNode $node)
    {
        $document = $node->ownerDocument;
        if (! $document instanceof Document) {
            $document = Document::fromNode($node);
        }

        return $document;
    }
}
