<?php
namespace Neos\Fusion\Afx\Tests\Functional;

use Neos\Fusion\Afx\Exception\AfxException;
use Neos\Fusion\Afx\Service\AfxService;
use PackageFactory\Afx\Exception as PackageFactoryAfxException;
use PHPUnit\Framework\TestCase;

class AfxServiceTest extends TestCase
{

    /**
     * @test
     */
    public function emptyCodeConvertedToEmptyFusion(): void
    {
        $afxCode = '';
        $expectedFusion = <<<'EOF'
''
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function whitespaceCodeIsConvertedToEmptyFusion(): void
    {
        $afxCode = '   ';
        $expectedFusion = <<<'EOF'
''
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function htmlTagsAreConvertedToFusionTags(): void
    {
        $afxCode = '<h1></h1>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function htmlTagsWithSpaceContentAreConvertedToFusionTags(): void
    {
        $afxCode = '<h1>   </h1>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = '   '
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function htmlTagsWithIgnoredContentAreConvertedToFusionTags(): void
    {
        $afxCode = '<h1>
   
</h1>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = ''
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function multipleHtmlTagsAreConvertedToFusionArray(): void
    {
        $afxCode = '<h1></h1><p></p><p></p>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Array {
    item_1 = Neos.Fusion:Tag {
        tagName = 'h1'
    }
    item_2 = Neos.Fusion:Tag {
        tagName = 'p'
    }
    item_3 = Neos.Fusion:Tag {
        tagName = 'p'
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function multipleHtmlTagsAndTextsAreConvertedToFusionArray(): void
    {
        $afxCode = 'Foo<h1></h1>Bar<p></p>Baz';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Array {
    item_1 = 'Foo'
    item_2 = Neos.Fusion:Tag {
        tagName = 'h1'
    }
    item_3 = 'Bar'
    item_4 = Neos.Fusion:Tag {
        tagName = 'p'
    }
    item_5 = 'Baz'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function whitepaceAroundAfxIsIgnored(): void
    {
        $afxCode = '  <h1></h1><p></p>  ';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Array {
    item_1 = Neos.Fusion:Tag {
        tagName = 'h1'
    }
    item_2 = Neos.Fusion:Tag {
        tagName = 'p'
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function multipleHtmlTagsAreConvertedToFusionTags(): void
    {
        $afxCode = '<h1></h1><p></p><p></p>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Array {
    item_1 = Neos.Fusion:Tag {
        tagName = 'h1'
    }
    item_2 = Neos.Fusion:Tag {
        tagName = 'p'
    }
    item_3 = Neos.Fusion:Tag {
        tagName = 'p'
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function whitespacesAndNewlinesAroundAfxCodeAreIgnored(): void
    {
        $afxCode = '   
              <h1></h1>
        ';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function htmlTagsAreConvertedToSelfClosingFusionTags(): void
    {
        $afxCode = '<h1/>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    selfClosingTag = true
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function attributesInHtmlTagsAreConvertedToTagAttributes(): void
    {
        $afxCode = '<h1 content="bar" class="fooo" />';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    selfClosingTag = true
    attributes.content = 'bar'
    attributes.class = 'fooo'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function sindgleQuotesAreEscapedInAttributesAndChildren(): void
    {
        $afxCode = '<h1 class="foo\'bar" >foo\'bar</h1>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    attributes.class = 'foo\'bar'
    content = 'foo\'bar'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function fusionTagsAreConvertedToFusionObjects(): void
    {
        $afxCode = '<Vendor.Site:Prototype/>';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function attributesInFusionTagsAreConvertedToFusionPropertiesOrEelExpressions(): void
    {
        $afxCode = '<Vendor.Site:Prototype foo="bar" baz="bam" />';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    foo = 'bar'
    baz = 'bam'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function metaAttributesOfFusionObjectTagsAreConvertedToFusionProperties(): void
    {
        $afxCode = '<Vendor.Site:Prototype @position="start" @if.hasTitle={title} />';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    @position = 'start'
    @if.hasTitle = ${title}
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function metaAttributesOfHtmlTagsAreConvertedToFusionProperties(): void
    {
        $afxCode = '<div @position="start" @if.hasTitle={title} ></div>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'div'
    @position = 'start'
    @if.hasTitle = ${title}
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function contentOfHtmlTagsIsRenderedAsFusionContent(): void
    {
        $afxCode = '<h1>Fooo</h1>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = 'Fooo'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function contentOfFusionTagsIsRenderedAsFusionRenderer(): void
    {
        $afxCode = '<Vendor.Site:Prototype>Fooo</Vendor.Site:Prototype>';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    content = 'Fooo'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function textContentOfHtmlTagsIsRenderedAsConfiguredChildrenProperty(): void
    {
        $afxCode = '<Vendor.Site:Prototype @children="children">Fooo</Vendor.Site:Prototype>';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    children = 'Fooo'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function eelContentOfHtmlTagsIsRenderedAsConfiguredChildrenProperty(): void
    {
        $afxCode = '<Vendor.Site:Prototype @children="children">{eelExpression()}</Vendor.Site:Prototype>';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    children = ${eelExpression()}
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function complexChildrenAreRenderedAsArray(): void
    {
        $afxCode = '<h1><strong>foo</strong><i>bar</i></h1>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = Neos.Fusion:Array {
        item_1 = Neos.Fusion:Tag {
            tagName = 'strong'
            content = 'foo'
        }
        item_2 = Neos.Fusion:Tag {
            tagName = 'i'
            content = 'bar'
        }
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function complexChildrenAreRenderedAsArrayIgnoringWhitespaceInBetween(): void
    {
        $afxCode = <<<'EOF'
<h1>
    
    <strong>foo</strong>
    
    <i>bar</i>
        
</h1>
EOF;

        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = Neos.Fusion:Array {
        item_1 = Neos.Fusion:Tag {
            tagName = 'strong'
            content = 'foo'
        }
        item_2 = Neos.Fusion:Tag {
            tagName = 'i'
            content = 'bar'
        }
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function complexChildrenAreRenderedAsArrayWithOptionalKeys(): void
    {
        $afxCode = '<h1><strong @key="key_one">foo</strong><i @key="key_two">bar</i></h1>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = Neos.Fusion:Array {
        key_one = Neos.Fusion:Tag {
            tagName = 'strong'
            content = 'foo'
        }
        key_two = Neos.Fusion:Tag {
            tagName = 'i'
            content = 'bar'
        }
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function complexChildrenAreCanContainTagsAnsValues(): void
    {
        $afxCode = '<h1>a string<strong>a tag</strong>{eelExpression()}</h1>';
        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = Neos.Fusion:Array {
        item_1 = 'a string'
        item_2 = Neos.Fusion:Tag {
            tagName = 'strong'
            content = 'a tag'
        }
        item_3 = ${eelExpression()}
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function childrenWithPathesAreRendered(): void
    {
        $afxCode = '<Vendor.Site:Prototype><strong @path="namedProp">foo</strong></Vendor.Site:Prototype>';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    namedProp = Neos.Fusion:Tag {
        tagName = 'strong'
        content = 'foo'
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function multipleChildrenWithPathesAreRendered(): void
    {
        $afxCode = '<Vendor.Site:Prototype><strong @path="propOne">foo</strong><Vendor.Site:Prototype @path="propTwo">bar</Vendor.Site:Prototype></Vendor.Site:Prototype>';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    propOne = Neos.Fusion:Tag {
        tagName = 'strong'
        content = 'foo'
    }
    propTwo = Vendor.Site:Prototype {
        content = 'bar'
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function childrenWithPathesAreCompatibleWithContentChildren(): void
    {
        $afxCode = '<Vendor.Site:Prototype><strong @path="propOne">foo</strong><Vendor.Site:Prototype @path="propTwo">bar</Vendor.Site:Prototype><div>a tag</div><div>another tag</div></Vendor.Site:Prototype>';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    propOne = Neos.Fusion:Tag {
        tagName = 'strong'
        content = 'foo'
    }
    propTwo = Vendor.Site:Prototype {
        content = 'bar'
    }
    content = Neos.Fusion:Array {
        item_1 = Neos.Fusion:Tag {
            tagName = 'div'
            content = 'a tag'
        }
        item_2 = Neos.Fusion:Tag {
            tagName = 'div'
            content = 'another tag'
        }
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function childrenWithDeepPathesAreSupported(): void
    {
        $afxCode = '<Vendor.Site:Prototype><strong @path="a.fusion.path">foo</strong><Vendor.Site:Prototype @path="another.fusion.path">bar</Vendor.Site:Prototype></Vendor.Site:Prototype>';
        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    a.fusion.path = Neos.Fusion:Tag {
        tagName = 'strong'
        content = 'foo'
    }
    another.fusion.path = Vendor.Site:Prototype {
        content = 'bar'
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function spacesNewLinesAndSpacesAroundAreIgnored(): void
    {
        $afxCode = '<h1>
            {eelExpression1}
            {eelExpression2}
            {eelExpression3}
        </h1>';

        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = Neos.Fusion:Array {
        item_1 = ${eelExpression1}
        item_2 = ${eelExpression2}
        item_3 = ${eelExpression3}
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function spacesInsideALineArePreserved(): void
    {
        $afxCode = '<h1>
            {eelExpression1} {eelExpression2}
        </h1>';

        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = Neos.Fusion:Array {
        item_1 = ${eelExpression1}
        item_2 = ' '
        item_3 = ${eelExpression2}
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function spacesInsideALineArePreservedAlsoForStrings(): void
    {
        $afxCode = '<h1>
            String {eelExpression} String
        </h1>';

        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = Neos.Fusion:Array {
        item_1 = 'String '
        item_2 = ${eelExpression}
        item_3 = ' String'
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function spreadsAreEvaluatedForFusionObjectTags(): void
    {
        $afxCode = '<Vendor.Site:Prototype {...spreadExpression} />';

        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    @apply.spread_1 = ${spreadExpression}
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function spreadsCanMixWithPropsForFusionObjectTags(): void
    {
        $afxCode = '<Vendor.Site:Prototype stringBefore="string" expressionBefore={expression} {...spreadExpression} stringAfter="string" expressionAfter={expression} />';

        $expectedFusion = <<<'EOF'
Vendor.Site:Prototype {
    stringBefore = 'string'
    expressionBefore = ${expression}
    @apply.spread_1 = ${spreadExpression}
    @apply.spread_2 = Neos.Fusion:RawArray {
        stringAfter = 'string'
        expressionAfter = ${expression}
    }
}
EOF;

        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function spreadsAreEvaluetedForHtmlTags(): void
    {
        $afxCode = '<h1 {...spreadExpression} />';

        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    selfClosingTag = true
    attributes.@apply.spread_1 = ${spreadExpression}
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function spreadsCanMixWithPropsForHtmlTags(): void
    {
        $afxCode = '<h1 stringBefore="string" expressionBefore={expression} {...spreadExpression} stringAfter="string" expressionAfter={expression} />';

        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    selfClosingTag = true
    attributes.stringBefore = 'string'
    attributes.expressionBefore = ${expression}
    attributes.@apply.spread_1 = ${spreadExpression}
    attributes.@apply.spread_2 = Neos.Fusion:RawArray {
        stringAfter = 'string'
        expressionAfter = ${expression}
    }
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function slashesInTextNodesArePreserved(): void
    {
        $afxCode = '<h1>\o/</h1>';

        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = '\\o/'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function textsAreEscaped(): void
    {
        $afxCode = <<<'EOF'
<h1>foo'bar\baz"bam</h1>
EOF;

        $expectedFusion = <<<'EOF'
Neos.Fusion:Tag {
    tagName = 'h1'
    content = 'foo\'bar\\baz\"bam'
}
EOF;
        self::assertEquals($expectedFusion, AfxService::convertAfxToFusion($afxCode));
    }

    /**
     * @test
     */
    public function unclosedTagsRaisesException(): void
    {
        $this->expectException(PackageFactoryAfxException::class);
        $afxCode = '<h1>';
        AfxService::convertAfxToFusion($afxCode);
    }

    /**
     * @test
     */
    public function unclosedAttributeRaisesException(): void
    {
        $this->expectException(PackageFactoryAfxException::class);
        $afxCode = '<h1 foo="bar />';
        AfxService::convertAfxToFusion($afxCode);
    }

    /**
     * @test
     */
    public function unclosedExpressionRaisesException(): void
    {
        $this->expectException(PackageFactoryAfxException::class);
        $afxCode = '<h1 foo={"123" />';
        AfxService::convertAfxToFusion($afxCode);
    }

    /**
     * @test
     */
    public function unclosedSpreadRaisesException(): void
    {
        $this->expectException(PackageFactoryAfxException::class);
        $afxCode = '<h1 {...expression />';
        AfxService::convertAfxToFusion($afxCode);
    }

    /**
     * @test
     */
    public function childPathAnnotationWithExpressionRaisesException(): void
    {
        $this->expectException(AfxException::class);
        $afxCode = '<div><span @path={expression} /></div>';
        AfxService::convertAfxToFusion($afxCode);
    }

    /**
     * @test
     */
    public function keyAnnotationWithExpressionRaisesException(): void
    {
        $this->expectException(AfxException::class);
        $afxCode = '<div><span @key={expression} /><span/></div>';
        AfxService::convertAfxToFusion($afxCode);
    }

    /**
     * @test
     */
    public function childrenAnnotationWithExpressionRaisesException(): void
    {
        $this->expectException(AfxException::class);
        $afxCode = '<div @children={expression} ><span/></div>';
        AfxService::convertAfxToFusion($afxCode);
    }
}
