<?php

/**
 * DO NOT EDIT!
 * This file was automatically generated via bin/generate-validator-spec.php.
 */

namespace AmpProject\Validator\Spec\Tag;

use AmpProject\Format;
use AmpProject\Html\AtRule;
use AmpProject\Html\Attribute;
use AmpProject\Html\Tag as Element;
use AmpProject\Validator\Spec\AttributeList;
use AmpProject\Validator\Spec\Identifiable;
use AmpProject\Validator\Spec\SpecRule;
use AmpProject\Validator\Spec\Tag;

/**
 * Tag class StyleAmpCustom.
 *
 * @package ampproject/amp-toolbox.
 *
 * @property-read string $tagName
 * @property-read string $specName
 * @property-read bool $unique
 * @property-read string $mandatoryParent
 * @property-read array $attrs
 * @property-read array<string> $attrLists
 * @property-read string $specUrl
 * @property-read array $cdata
 * @property-read array<string> $htmlFormat
 * @property-read string $namedId
 * @property-read string $descriptiveName
 */
final class StyleAmpCustom extends Tag implements Identifiable
{
    /**
     * ID of the tag.
     *
     * @var string
     */
    const ID = 'style amp-custom';

    /**
     * Array of spec rules.
     *
     * @var array
     */
    const SPEC = [
        SpecRule::TAG_NAME => Element::STYLE,
        SpecRule::SPEC_NAME => 'style amp-custom',
        SpecRule::UNIQUE => true,
        SpecRule::MANDATORY_PARENT => Element::HEAD,
        SpecRule::ATTRS => [
            Attribute::AMP_CUSTOM => [
                SpecRule::MANDATORY => true,
                SpecRule::VALUE => [
                    '',
                ],
            ],
            Attribute::TYPE => [
                SpecRule::VALUE_CASEI => [
                    'text/css',
                ],
            ],
        ],
        SpecRule::ATTR_LISTS => [
            AttributeList\NonceAttr::ID,
        ],
        SpecRule::SPEC_URL => 'https://amp.dev/documentation/guides-and-tutorials/learn/spec/amphtml/#stylesheets',
        SpecRule::CDATA => [
            SpecRule::MAX_BYTES => 75000,
            SpecRule::MAX_BYTES_SPEC_URL => 'https://amp.dev/documentation/guides-and-tutorials/learn/spec/amphtml/#maximum-size',
            SpecRule::DISALLOWED_CDATA_REGEX => [
                [
                    SpecRule::REGEX => '<!--',
                    SpecRule::ERROR_MESSAGE => 'html comments',
                ],
                [
                    SpecRule::REGEX => '(^|\W)i-amphtml-',
                    SpecRule::ERROR_MESSAGE => 'CSS i-amphtml- name prefix',
                ],
            ],
            SpecRule::CSS_SPEC => [
                SpecRule::AT_RULE_SPEC => [
                    [
                        SpecRule::NAME => AtRule::FONT_FACE,
                    ],
                    [
                        SpecRule::NAME => AtRule::KEYFRAMES,
                    ],
                    [
                        SpecRule::NAME => AtRule::MEDIA,
                        SpecRule::MEDIA_QUERY_SPEC => [
                            SpecRule::ISSUES_AS_ERROR => false,
                            SpecRule::TYPE => [
                                'all',
                                'print',
                                'screen',
                                'speech',
                                'tty',
                                'tv',
                                'projection',
                                'handheld',
                                'braille',
                                'embossesd',
                                'aural',
                                '-sass-debug-info',
                                'device-pixel-ratio',
                                'device-pixel-ratio2',
                            ],
                            SpecRule::FEATURE => [
                                'any-hover',
                                'any-pointer',
                                'aspect-ratio',
                                'color',
                                'color-gamut',
                                'color-index',
                                'device-aspect-ratio',
                                'device-height',
                                'device-width',
                                'display-mode',
                                'forced-colors',
                                'grid',
                                'height',
                                'hover',
                                'inverted-colors',
                                'light-level',
                                'max-aspect-ratio',
                                'max-color-index',
                                'max-device-aspect-ratio',
                                'max-device-height',
                                'max-device-width',
                                'max-height',
                                'max-resolution',
                                'max-width',
                                'min-aspect-ratio',
                                'min-color-index',
                                'min-device-aspect-ratio',
                                'min-device-height',
                                'min-device-width',
                                'min-height',
                                'min-resolution',
                                'min-width',
                                'monochrome',
                                'orientation',
                                'overflow-block',
                                'overflow-inline',
                                'pointer',
                                'prefers-color-scheme',
                                'prefers-contrast',
                                'prefers-reduced-motion',
                                'prefers-reduced-transparency',
                                'resolution',
                                'scan',
                                'scripting',
                                'transform-3d',
                                'update',
                                'width',
                                '--mod',
                                '--md',
                                'device-pixel-ratio',
                                'device-pixel-ratio2',
                                'high-contrast',
                                'max-device-pixel-ratio',
                                'min-device-pixel-ratio',
                                'max-device-pixel-ratio2',
                                'min-device-pixel-ratio2',
                                'min--moz-device-pixel-ratio',
                                'max--moz-device-pixel-ratio',
                            ],
                        ],
                    ],
                    [
                        SpecRule::NAME => AtRule::PAGE,
                    ],
                    [
                        SpecRule::NAME => AtRule::SUPPORTS,
                    ],
                    [
                        SpecRule::NAME => AtRule::_MOZ_DOCUMENT,
                    ],
                ],
            ],
            SpecRule::DOC_CSS_BYTES => true,
        ],
        SpecRule::HTML_FORMAT => [
            Format::AMP,
        ],
        SpecRule::NAMED_ID => 'STYLE_AMP_CUSTOM',
        SpecRule::DESCRIPTIVE_NAME => 'style amp-custom',
    ];
}
