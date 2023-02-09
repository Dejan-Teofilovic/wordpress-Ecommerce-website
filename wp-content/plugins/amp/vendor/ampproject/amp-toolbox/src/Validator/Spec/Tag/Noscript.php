<?php

/**
 * DO NOT EDIT!
 * This file was automatically generated via bin/generate-validator-spec.php.
 */

namespace AmpProject\Validator\Spec\Tag;

use AmpProject\Format;
use AmpProject\Html\Tag as Element;
use AmpProject\Validator\Spec\Identifiable;
use AmpProject\Validator\Spec\SpecRule;
use AmpProject\Validator\Spec\Tag;

/**
 * Tag class Noscript.
 *
 * @package ampproject/amp-toolbox.
 *
 * @property-read string $tagName
 * @property-read array<string> $disallowedAncestor
 * @property-read string $mandatoryAncestor
 * @property-read array<string> $htmlFormat
 */
final class Noscript extends Tag implements Identifiable
{
    /**
     * ID of the tag.
     *
     * @var string
     */
    const ID = 'NOSCRIPT';

    /**
     * Array of spec rules.
     *
     * @var array
     */
    const SPEC = [
        SpecRule::TAG_NAME => Element::NOSCRIPT,
        SpecRule::DISALLOWED_ANCESTOR => [
            'NOSCRIPT',
        ],
        SpecRule::MANDATORY_ANCESTOR => Element::BODY,
        SpecRule::HTML_FORMAT => [
            Format::AMP,
        ],
    ];
}
