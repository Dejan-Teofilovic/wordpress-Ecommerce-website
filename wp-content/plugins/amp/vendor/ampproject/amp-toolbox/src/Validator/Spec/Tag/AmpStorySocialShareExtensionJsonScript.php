<?php

/**
 * DO NOT EDIT!
 * This file was automatically generated via bin/generate-validator-spec.php.
 */

namespace AmpProject\Validator\Spec\Tag;

use AmpProject\Extension;
use AmpProject\Format;
use AmpProject\Html\Attribute;
use AmpProject\Html\Tag as Element;
use AmpProject\Validator\Spec\AttributeList;
use AmpProject\Validator\Spec\Identifiable;
use AmpProject\Validator\Spec\SpecRule;
use AmpProject\Validator\Spec\Tag;

/**
 * Tag class AmpStorySocialShareExtensionJsonScript.
 *
 * @package ampproject/amp-toolbox.
 *
 * @property-read string $tagName
 * @property-read string $specName
 * @property-read bool $unique
 * @property-read string $mandatoryParent
 * @property-read array<array> $attrs
 * @property-read array<string> $attrLists
 * @property-read array<string> $htmlFormat
 * @property-read array<string> $requiresExtension
 * @property-read bool $siblingsDisallowed
 * @property-read bool $mandatoryLastChild
 */
final class AmpStorySocialShareExtensionJsonScript extends Tag implements Identifiable
{
    /**
     * ID of the tag.
     *
     * @var string
     */
    const ID = 'amp-story-social-share extension .json script';

    /**
     * Array of spec rules.
     *
     * @var array
     */
    const SPEC = [
        SpecRule::TAG_NAME => Element::SCRIPT,
        SpecRule::SPEC_NAME => 'amp-story-social-share extension .json script',
        SpecRule::UNIQUE => true,
        SpecRule::MANDATORY_PARENT => Extension::STORY_SOCIAL_SHARE,
        SpecRule::ATTRS => [
            Attribute::TYPE => [
                SpecRule::MANDATORY => true,
                SpecRule::DISPATCH_KEY => 'NAME_VALUE_PARENT_DISPATCH',
                SpecRule::VALUE_CASEI => [
                    'application/json',
                ],
            ],
        ],
        SpecRule::ATTR_LISTS => [
            AttributeList\NonceAttr::ID,
        ],
        SpecRule::HTML_FORMAT => [
            Format::AMP,
        ],
        SpecRule::REQUIRES_EXTENSION => [
            Extension::STORY,
        ],
        SpecRule::SIBLINGS_DISALLOWED => true,
        SpecRule::MANDATORY_LAST_CHILD => true,
    ];
}
