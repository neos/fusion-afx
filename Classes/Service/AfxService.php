<?php
namespace Neos\Fusion\Afx\Service;

/*
 * This file is part of the Neos.Fusion.Afx package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Fusion\Afx\Parser\AfxParserException;
use Neos\Fusion\Afx\Parser\Parser as AfxParser;
use Neos\Fusion\Afx\Exception\AfxException;

/**
 * Class AfxService
 *
 * @Flow\Scope("singleton")
 */
class AfxService
{
    protected const INDENTATION = '    ';
    protected const SHORTHAND_META_PATHS = ['@if', '@process'];

    /**
     * @var string $afxCode the AFX code that is converted
     * @var string $indentation Indentation to start with
     * @return string
     * @throws AfxException
     * @throws AfxParserException
     */
    public static function convertAfxToFusion(string $afxCode, string $indentation = ''): string
    {
        $parser = new AfxParser(trim($afxCode));
        $ast = $parser->parse();
        return self::astNodeListToFusion($ast, $indentation);
    }

    protected static function astToFusion(array $ast, string $indentation = ''): string
    {
        return match ($ast['type']) {
            'expression' => self::astExpressionToFusion($ast['payload']),
            'string' => self::astStringToFusion($ast['payload']),
            'text' => self::astTextToFusion($ast['payload']),
            'boolean' => self::astBooleanToFusion($ast['payload']),
            'node' => self::astNodeToFusion($ast['payload'], $indentation),
            default => throw new AfxException(sprintf('ast type %s is unknown', $ast['type'])),
        };
    }

    protected static function astBooleanToFusion(bool $payload): string
    {
        return $payload ? 'true' : 'false';
    }

    protected static function astExpressionToFusion(string $payload): string
    {
        return '${' . $payload . '}';
    }

    protected static function astStringToFusion(string $payload): string
    {
        return '\'' . addslashes($payload) . '\'';
    }

    protected static function astTextToFusion(string $payload): string
    {
        return '\'' . addslashes($payload) . '\'';
    }

    protected static function propToFusion(array $attribute, string $attributePrefix, string $indentation = ''): string
    {
        $prop = $attribute['payload'];
        $propName = $prop['identifier'];
        $propFusion = self::astToFusion($prop, $indentation . self::INDENTATION);
        return $indentation . self::INDENTATION . $attributePrefix . $propName . ' = ' . $propFusion . PHP_EOL;
    }

    protected static function propListToFusion(array $payload, string $attributePrefix, string $indentation = ''): string
    {
        $fusion = '';
        foreach ($payload as $attribute) {
            if ($attribute['type'] === 'prop') {
                $fusion .= self::propToFusion($attribute, $attributePrefix, $indentation);
            }
        }
        return $fusion;
    }

    protected static function astNodeToFusion(array $payload, string $indentation = ''): string
    {
        $tagName = $payload['identifier'];
        $attributes = $payload['attributes'];

        // Tag
        if (str_contains($tagName, ':')) {
            // Named fusion-object
            $fusion = $tagName . ' {' . PHP_EOL;
            // Attributes are not prefixed
            $attributePrefix = '';
        } else {
            // Neos.Fusion:Tag
            $fusion = 'Neos.Fusion:Tag {' . PHP_EOL;
            $fusion .= $indentation . self::INDENTATION . 'tagName = \'' .  $tagName . '\'' . PHP_EOL;
            // Attributes are rendered as tag-attributes
            $attributePrefix = 'attributes.';
            // Self closing Tags stay self closing
            if ($payload['selfClosing'] === true) {
                $fusion .= $indentation . self::INDENTATION . 'selfClosingTag = true' . PHP_EOL;
            }
        }

        $childrenPropertyName = 'content';
        $attributes =
            self::attributesSortPropsAndGeneratePropLists(
                self::attributesGeneratePathForShorthandFusionMetaPath(
                    self::attributesRemoveKeyAndPathAndExtractChildrenName(
                        $attributes,
                        $childrenPropertyName
                    )
                )
            );

        $fusion .= self::renderFusionAttributes(
            $attributes,
            $attributePrefix,
            $indentation,
        );

        // filter children into path and content children
        $pathChildren = [];
        $contentChildren = [];
        if ($payload['children'] && count($payload['children']) > 0) {
            foreach ($payload['children'] as $child) {
                if ($child['type'] === 'node') {
                    $path = null;
                    foreach ($child['payload']['attributes'] as $attribute) {
                        if ($attribute['type'] === 'prop' && $attribute['payload']['identifier'] === '@path') {
                            $pathAttribute = $attribute['payload'];
                            if ($pathAttribute['type'] !== 'string') {
                                throw new AfxException(sprintf('@path only supports string payloads %s found', $pathAttribute['type']));
                            }
                            $path = $pathAttribute['payload'];
                        }
                    }

                    if (isset($path)) {
                        $pathChildren[$path] = $child;
                        continue;
                    }
                }
                $contentChildren[] = $child;
            }
        }

        // Path Children
        if ($pathChildren !== []) {
            foreach ($pathChildren as $path => $child) {
                $fusion .= $indentation . self::INDENTATION . $path . ' = ' . self::astToFusion($child, $indentation . self::INDENTATION) . PHP_EOL;
            }
        }

        // Content Children
        if ($contentChildren !== []) {
            $childFusion = self::astNodeListToFusion($contentChildren, $indentation . self::INDENTATION);
            $fusion .= $indentation . self::INDENTATION . $childrenPropertyName . ' = ' . $childFusion . PHP_EOL;
        }

        $fusion .= $indentation . '}';

        return $fusion;
    }

    protected static function astNodeListToFusion(array $payload, string $indentation = ''): string
    {
        // ignore blank text if it is connected to a newline
        $payload = array_map(function ($astNode) {
            if ($astNode['type'] === 'text') {
                // remove whitespace connected to newlines at the beginning or end of text payloads
                $astNode['payload'] = preg_replace('/^[\\s]*\\n[\\s]*|[\\s]*\\n[\\s]*$/u', '', $astNode['payload']);
                // collapse whitespace connected to newlines in the middle of text payloads to spaces
                $astNode['payload'] = preg_replace('/([\\s]*\\n[\\s]*)+/u', ' ', $astNode['payload']);
            }
            return $astNode;
        }, $payload);

        // filter empty text nodes and comments
        $payload = array_filter($payload, function ($astNode) {
            if ($astNode['type'] === 'text' && $astNode['payload'] === '') {
                return false;
            }
            if ($astNode['type'] === 'comment') {
                return false;
            }
            return true;
        });

        if (count($payload) === 0) {
            return "''";
        }

        if (count($payload) === 1) {
            return self::astToFusion(array_shift($payload), $indentation);
        }

        $index = 0;
        $fusion = 'Neos.Fusion:Join {' . PHP_EOL;
        foreach ($payload as $astNode) {
            // detect key
            $fusionName = 'item_' . ++$index;
            if ($astNode['type'] === 'node') {
                foreach ($astNode['payload']['attributes'] as $attribute) {
                    if ($attribute['type'] === 'prop'
                        && $attribute['payload']['identifier'] === '@key') {
                        if ($attribute['payload']['type'] !== 'string') {
                            throw new AfxException(sprintf(
                                '@key only supports string payloads %s was given',
                                $attribute['payload']['type']
                            ));
                        }
                        $fusionName = $attribute['payload']['payload'];
                    }
                }
            }

            // convert node
            $nodeFusion = self::astToFusion($astNode, $indentation . self::INDENTATION);
            $fusion .= $indentation . self::INDENTATION . $fusionName . ' = ' . $nodeFusion . PHP_EOL;
        }
        $fusion .= $indentation . '}';
        return $fusion;
    }

    protected static function renderFusionAttributes(iterable $attributes, string $attributePrefix, string $indentation): string
    {
        $fusion = '';
        $spreadIndex = 0;
        foreach ($attributes as $attribute) {
            switch ($attribute['type']) {
                case 'prop':
                    $isMeta = $attribute['payload']['identifier'][0] === '@';
                    $fusion .= self::propToFusion($attribute, $isMeta ? '' : $attributePrefix);
                    break;
                case 'spread':
                    if ($attribute['payload']['type'] !== 'expression') {
                        throw new AfxException(sprintf('Spreads only support expression payloads %s found', $attribute['payload']['type']));
                    }
                    $spreadFusion = self::astToFusion($attribute['payload'], $indentation . self::INDENTATION);
                    $fusion .= $indentation . self::INDENTATION . $attributePrefix . '@apply.spread_' . ++$spreadIndex . ' = ' . $spreadFusion . PHP_EOL;
                    break;
                case 'propList':
                    $fusion .= $indentation . self::INDENTATION . $attributePrefix . '@apply.spread_' . ++$spreadIndex . ' = Neos.Fusion:DataStructure {' . PHP_EOL;
                    $fusion .= self::propListToFusion($attribute['payload'], '', $indentation . self::INDENTATION);
                    $fusion .= $indentation . self::INDENTATION . '}' . PHP_EOL;
                    break;
            }
        }
        return $fusion;
    }

    protected static function attributesSortPropsAndGeneratePropLists(iterable $attributes): \Generator
    {
        $delayedMetaAttributes = [];
        $spreadIsPresent = false;
        $currentPropListAfterSpread = null;

        foreach ($attributes as $attribute) {
            // <div @if={true} />
            // meta attributes are rendered last
            if ($attribute['type'] === 'prop'
                && $attribute['payload']['identifier'][0] === '@') {
                $delayedMetaAttributes[] = $attribute;
                continue;
            }

            // <div a b />
            // attributes before the first spread are render as fusion keys
            if ($spreadIsPresent === false && $attribute['type'] === 'prop') {
                yield $attribute;
                continue;
            }

            // <div {...spread1} {...spread2} />
            // a spread and directly following spreads without attributes in between
            // are rendered as @apply
            if ($currentPropListAfterSpread === null && $attribute['type'] === 'spread') {
                $spreadIsPresent = true;
                yield $attribute;
                continue;
            }

            // <div {...spread1} a />
            // attributes after a spread are rendered also as @apply, so they can override the spread
            // (collect them and render them grouped in a Neos.Fusion:DataStructure)
            if ($spreadIsPresent && $attribute['type'] === 'prop') {
                $currentPropListAfterSpread ??= [
                    'type' => 'propList',
                    'payload' => []
                ];
                $currentPropListAfterSpread['payload'][] = $attribute;
                continue;
            }

            // <div {...spread1} a b {...spread2} />
            // collected attributes after a spread are rendered as @apply and then the next spread itself
            if ($currentPropListAfterSpread !== null && $attribute['type'] === 'spread') {
                yield $currentPropListAfterSpread;
                $currentPropListAfterSpread = null;
                yield $attribute;
                continue;
            }
        }

        // <div {...spread1} a b />
        // collected attributes when there is no spread after them
        if ($currentPropListAfterSpread !== null) {
            yield $currentPropListAfterSpread;
            $currentPropListAfterSpread = null;
        }

        yield from $delayedMetaAttributes;
    }

    protected static function attributesGeneratePathForShorthandFusionMetaPath(iterable $attributes): \Generator
    {
        // holds the incrementing index per attribute path
        $indexPerAttributePath = [];

        foreach ($attributes as $attribute) {
            if ($attribute['type'] !== 'prop') {
                yield $attribute;
                continue;
            }
            $path = &$attribute['payload']['identifier'];

            $fusionPropertyPathSegments = explode('.', $path);
            // f.x. '@if' (when shorthand) or 'hasTitle' (when no shorthand)
            $lastPathSegment = end($fusionPropertyPathSegments);

            if (in_array($lastPathSegment, self::SHORTHAND_META_PATHS, true)) {
                if (isset($indexPerAttributePath[$path]) === false) {
                    $indexPerAttributePath[$path] = 0;
                }

                // add f.x. '.if_1'
                $path .= '.' . substr($lastPathSegment, 1) . '_' . ++$indexPerAttributePath[$path];
            }
            yield $attribute;
        }
    }

    /**
     * filter attributes and remove {@key}, {@path} and {@children} attributes
     * also extract the {@children} to the reference param
     *
     */
    protected static function attributesRemoveKeyAndPathAndExtractChildrenName(iterable $attributes, string &$childrenPropertyName): \Generator
    {
        foreach ($attributes as $attribute) {
            if ($attribute['type'] !== 'prop') {
                yield $attribute;
                continue;
            }

            if ($attribute['payload']['identifier'] === '@key'
                || $attribute['payload']['identifier'] === '@path') {
                continue;
            }

            if ($attribute['payload']['identifier'] === '@children') {
                if ($attribute['payload']['type'] !== 'string') {
                    throw new AfxException(sprintf('@children only supports string payloads %s found', $attribute['payload']['type']));
                }
                $childrenPropertyName = $attribute['payload']['payload'];
                continue;
            }

            yield $attribute;
        }
    }
}
