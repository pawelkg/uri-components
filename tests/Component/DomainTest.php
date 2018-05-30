<?php

/**
 * League.Uri (http://uri.thephpleague.com).
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    2.0.0
 * @link       https://github.com/thephpleague/uri-schemes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Uri\Component;

use ArrayIterator;
use League\Uri\Component\Domain;
use League\Uri\Exception\InvalidKey;
use League\Uri\Exception\InvalidUriComponent;
use League\Uri\Exception\UnknownEncoding;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group host
 * @coversDefaultClass \League\Uri\Component\Domain
 */
class DomainTest extends TestCase
{
    /**
     * @covers ::__set_state
     */
    public function testSetState()
    {
        $host = new Domain('uri.thephpleague.com');
        $this->assertEquals($host, eval('return '.var_export($host, true).';'));
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $component = new Domain('uri.thephpleague.com');
        $debugInfo = $component->__debugInfo();
        $this->assertArrayHasKey('component', $debugInfo);
        $this->assertSame($component->getContent(), $debugInfo['component']);
    }

    /**
     * @covers ::getIterator
     */
    public function testIterator()
    {
        $host = new Domain('uri.thephpleague.com');
        $this->assertEquals(['com', 'thephpleague', 'uri'], iterator_to_array($host));
    }

    /**
     * @covers ::__construct
     * @covers ::withContent
     * @covers ::parse
     */
    public function testWithContent()
    {
        $host = new Domain('uri.thephpleague.com');
        $this->assertSame($host, $host->withContent('uri.thephpleague.com'));
        $this->assertSame($host, $host->withContent($host));
        $this->assertNotSame($host, $host->withContent('csv.thephpleague.com'));
    }

    /**
     * Test valid Domain.
     * @param string|null $host
     * @param string      $uri
     * @param string      $iri
     * @dataProvider validDomainProvider
     * @covers ::__construct
     * @covers ::parse
     * @covers ::getContent
     * @covers ::getUriComponent
     */
    public function testValidDomain($host, $uri, $iri)
    {
        $host = new Domain($host);
        $this->assertSame($uri, $host->getUriComponent());
        $this->assertSame($iri, $host->getContent(Domain::RFC3987_ENCODING));
    }

    public function validDomainProvider()
    {
        return [
            'normalized' => [
                'Master.EXAMPLE.cOm',
                'master.example.com',
                'master.example.com',
            ],
            'empty string' => [
                '',
                '',
                '',
            ],
            'null' => [
                null,
                '',
                null,
            ],
            'dot ending' => [
                'example.com.',
                'example.com.',
                'example.com.',
            ],
            'partial numeric' => [
                '23.42c.two',
                '23.42c.two',
                '23.42c.two',
            ],
            'all numeric' => [
                '98.3.2',
                '98.3.2',
                '98.3.2',
            ],
            'mix IP format with host label' => [
                'toto.127.0.0.1',
                'toto.127.0.0.1',
                'toto.127.0.0.1',
            ],
            'idn support' => [
                'مثال.إختبار',
                'xn--mgbh0fb.xn--kgbechtv',
                'مثال.إختبار',
            ],
            'IRI support' => [
                'xn--mgbh0fb.xn--kgbechtv',
                'xn--mgbh0fb.xn--kgbechtv',
                'مثال.إختبار',
            ],
        ];
    }

    /**
     * @param string $invalid
     * @dataProvider invalidDomainProvider
     * @covers ::__construct
     * @covers ::parse
     * @covers ::getIDNAErrors
     */
    public function testInvalidDomain($invalid)
    {
        $this->expectException(InvalidUriComponent::class);
        new Domain($invalid);
    }

    public function invalidDomainProvider()
    {
        return [
            'ipv4' => ['127.0.0.1'],
            'ipv6' => ['::1'],
            'empty label' => ['tot.    .coucou.com'],
            'space in the label' => ['re view'],
            'Invalid IPv4 format' => ['[127.0.0.1]'],
            'Invalid IPv6 format' => ['[[::1]]'],
            'Invalid IPv6 format 2' => ['[::1'],
            'naked ipv6' => ['::1'],
            'scoped naked ipv6' => ['fe80:1234::%251'],
            'invalid character in scope ipv6' => ['[fe80:1234::%25%23]'],
            'space character in starting label' => ['example. com'],
            'invalid character in host label' => ["examp\0le.com"],
            'invalid IP with scope' => ['[127.2.0.1%253]'],
            'invalid scope IPv6' => ['[ab23::1234%251]'],
            'invalid scope ID' => ['[fe80::1234%25?@]'],
            'invalid scope ID with utf8 character' => ['[fe80::1234%25€]'],
            'invalid IPFuture' => ['[v4.1.2.3]'],
            'invalid host with mix content' => ['_b%C3%A9bé.be-'],
            'invalid IDN domain' => ['a⒈com'],
        ];
    }

    public function testTypeErrorOnDomainConstruction()
    {
        $this->expectException(TypeError::class);
        new Domain(date_create());
    }

    /**
     * @covers ::getContent
     */
    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(UnknownEncoding::class);
        (new Domain('host'))->getContent(-1);
    }

    /**
     * @param string $raw
     * @param bool   $expected
     * @dataProvider isAbsoluteProvider
     * @covers ::isAbsolute
     */
    public function testIsAbsolute($raw, $expected)
    {
        $this->assertSame($expected, (new Domain($raw))->isAbsolute());
    }

    public function isAbsoluteProvider()
    {
        return [
            ['example.com.', true],
            ['example.com', false],
        ];
    }

    /**
     * Test Punycode support.
     *
     * @param string $unicode Unicode Domainname
     * @param string $ascii   Ascii Domainname
     * @dataProvider hostnamesProvider
     * @covers ::getContent
     */
    public function testValidUnicodeDomain($unicode, $ascii)
    {
        $host = new Domain($unicode);
        $this->assertSame($ascii, $host->getContent(Domain::RFC3986_ENCODING));
        $this->assertSame($unicode, $host->getContent(Domain::RFC3987_ENCODING));
    }

    public function hostnamesProvider()
    {
        // http://en.wikipedia.org/wiki/.test_(international_domain_name)#Test_TLDs
        return [
            ['مثال.إختبار', 'xn--mgbh0fb.xn--kgbechtv'],
            ['مثال.آزمایشی', 'xn--mgbh0fb.xn--hgbk6aj7f53bba'],
            ['例子.测试', 'xn--fsqu00a.xn--0zwm56d'],
            ['例子.測試', 'xn--fsqu00a.xn--g6w251d'],
            ['пример.испытание', 'xn--e1afmkfd.xn--80akhbyknj4f'],
            ['उदाहरण.परीक्षा', 'xn--p1b6ci4b4b3a.xn--11b5bs3a9aj6g'],
            ['παράδειγμα.δοκιμή', 'xn--hxajbheg2az3al.xn--jxalpdlp'],
            ['실례.테스트', 'xn--9n2bp8q.xn--9t4b11yi5a'],
            ['בײַשפּיל.טעסט', 'xn--fdbk5d8ap9b8a8d.xn--deba0ad'],
            ['例え.テスト', 'xn--r8jz45g.xn--zckzah'],
            ['உதாரணம்.பரிட்சை', 'xn--zkc6cc5bi7f6e.xn--hlcj6aya9esc7a'],
            ['derhausüberwacher.de', 'xn--derhausberwacher-pzb.de'],
            ['renangonçalves.com', 'xn--renangonalves-pgb.com'],
            ['рф.ru', 'xn--p1ai.ru'],
            ['δοκιμή.gr', 'xn--jxalpdlp.gr'],
            ['ফাহাদ্১৯.বাংলা', 'xn--65bj6btb5gwimc.xn--54b7fta0cc'],
            ['𐌀𐌖𐌋𐌄𐌑𐌉·𐌌𐌄𐌕𐌄𐌋𐌉𐌑.gr', 'xn--uba5533kmaba1adkfh6ch2cg.gr'],
            ['guangdong.广东', 'guangdong.xn--xhq521b'],
            ['gwóźdź.pl', 'xn--gwd-hna98db.pl'],
        ];
    }

    /**
     * Test Countable.
     *
     * @param string|null $host
     * @param int         $nblabels
     * @param array       $array
     * @dataProvider countableProvider
     * @covers ::count
     */
    public function testCountable($host, $nblabels, $array)
    {
        $this->assertCount($nblabels, new Domain($host));
    }

    public function countableProvider()
    {
        return [
            'string' => ['secure.example.com', 3, ['com', 'example', 'secure']],
            'numeric' => ['92.56.8', 3, ['8', '56', '92']],
            'null' => [null, 0, []],
            'empty string' => ['', 1, ['']],
        ];
    }

    /**
     * @param mixed  $input
     * @param string $expected
     * @covers ::createFromLabels
     * @covers ::__toString
     *
     * @dataProvider createFromLabelsValid
     */
    public function testCreateFromLabels($input, $expected)
    {
        $this->assertSame($expected, (string) Domain::createFromLabels($input));
    }

    public function createFromLabelsValid()
    {
        return [
            'array' => [['com', 'example', 'www'], 'www.example.com'],
            'iterator' => [new ArrayIterator(['com', 'example', 'www']), 'www.example.com'],
            'FQDN' => [['', 'com', 'example', 'www'], 'www.example.com.'],
            'empty' => [[''], ''],
            'null' => [[], ''],
            'another host object' => [new Domain('example.com.'), 'example.com.'],
        ];
    }

    /**
     * @covers ::createFromLabels
     */
    public function testcreateFromLabelsFailedWithInvalidInput()
    {
        $this->expectException(TypeError::class);
        Domain::createFromLabels(date_create());
    }

    /**
     * @covers ::createFromLabels
     */
    public function testcreateFromLabelsFailedWithInvalidArrayInput()
    {
        $this->expectException(InvalidUriComponent::class);
        Domain::createFromLabels([date_create()]);
    }

    /**
     * @covers ::get
     */
    public function testGet()
    {
        $host = new Domain('master.example.com');
        $this->assertSame('com', $host->get(0));
        $this->assertSame('example', $host->get(1));
        $this->assertSame('master', $host->get(-1));
        $this->assertNull($host->get(23));
    }

    /**
     * @covers ::keys
     */
    public function testOffsets()
    {
        $host = new Domain('master.example.com');
        $this->assertSame([2], $host->keys('master'));
    }

    /**
     * @param string $host
     * @param int    $without
     * @param string $res
     * @dataProvider withoutProvider
     * @covers ::withoutLabel
     */
    public function testWithout($host, $without, $res)
    {
        $this->assertSame($res, (string) (new Domain($host))->withoutLabel($without));
    }

    public function withoutProvider()
    {
        return [
            //'remove unknown label' => ['secure.example.com', 34, 'secure.example.com'],
            'remove one string label' => ['secure.example.com', 0, 'secure.example'],
            'remove one string label negative offset' => ['secure.example.com', -1, 'example.com'],
            'remove simple label' => ['localhost', -1, ''],
        ];
    }

    /**
     * @covers ::withoutLabel
     */
    public function testWithoutTriggersException()
    {
        $this->expectException(InvalidKey::class);
        (new Domain('bébé.be'))->withoutLabel(-23);
    }

    /**
     * @covers ::prepend
     *
     * @param string $raw
     * @param string $prepend
     * @param string $expected
     *
     * @dataProvider validPrepend
     */
    public function testPrepend($raw, $prepend, $expected)
    {
        $this->assertSame($expected, (string) (new Domain($raw))->prepend($prepend));
    }

    public function validPrepend()
    {
        return [
            ['secure.example.com', 'master', 'master.secure.example.com'],
            ['secure.example.com.', 'master', 'master.secure.example.com.'],
            ['secure.example.com', '127.0.0.1', '127.0.0.1.secure.example.com'],
        ];
    }

    /**
     * @covers ::prepend
     */
    public function testPrependIpFailed()
    {
        $this->expectException(InvalidUriComponent::class);
        (new Domain('secure.example.com'))->prepend(new Domain('master.'));
    }

    /**
     * @covers ::append
     *
     * @param string $raw
     * @param string $append
     * @param string $expected
     *
     * @dataProvider validAppend
     */
    public function testAppend($raw, $append, $expected)
    {
        $this->assertSame($expected, (string) (new Domain($raw))->append($append));
    }

    public function validAppend()
    {
        return [
            ['secure.example.com', 'master', 'secure.example.com.master'],
            ['secure.example.com', 'master.', 'secure.example.com.master.'],
            ['toto', '127.0.0.1', 'toto.127.0.0.1'],
            ['example.com', '', 'example.com.'],
        ];
    }

    /**
     * @covers ::append
     */
    public function testAppendIpFailed()
    {
        $this->expectException(InvalidUriComponent::class);
        (new Domain('secure.example.com.'))->append('master');
    }

    /**
     * @param string $raw
     * @param string $input
     * @param int    $offset
     * @param string $expected
     * @dataProvider replaceValid
     * @covers ::withLabel
     * @covers ::append
     * @covers ::prepend
     */
    public function testReplace($raw, $input, $offset, $expected)
    {
        $this->assertSame($expected, (string) (new Domain($raw))->withLabel($offset, $input));
    }

    public function replaceValid()
    {
        return [
            ['master.example.com', 'shop', 3, 'master.example.com.shop'],
            ['master.example.com', 'shop', -4, 'shop.master.example.com'],
            ['master.example.com', 'shop', 2, 'shop.example.com'],
            ['master.example.com', 'master', 2, 'master.example.com'],
            ['secure.example.com', '127.0.0.1', 0, 'secure.example.127.0.0.1'],
            ['master.example.com.', 'shop', -2, 'master.shop.com.'],
            ['master.example.com', 'shop', -1, 'shop.example.com'],
            ['foo', 'bar', -1, 'bar'],
        ];
    }

    /**
     * @covers ::withLabel
     * @covers ::parse
     */
    public function testReplaceIpMustFailed()
    {
        $this->expectException(InvalidUriComponent::class);
        (new Domain('secure.example.com'))->withLabel(2, '[::1]');
    }

    /**
     * @covers ::withLabel
     * @covers ::parse
     */
    public function testReplaceMustFailed()
    {
        $this->expectException(InvalidKey::class);
        (new Domain('secure.example.com'))->withLabel(23, 'foo');
    }

    /**
     * @dataProvider rootProvider
     * @param string $host
     * @param string $expected_with_root
     * @param string $expected_without_root
     * @covers ::withRootLabel
     * @covers ::withoutRootLabel
     */
    public function testWithRoot($host, $expected_with_root, $expected_without_root)
    {
        $host = new Domain($host);
        $this->assertSame($expected_with_root, (string) $host->withRootLabel());
        $this->assertSame($expected_without_root, (string) $host->withoutRootLabel());
    }

    public function rootProvider()
    {
        return [
            ['example.com', 'example.com.', 'example.com'],
            ['example.com.', 'example.com.', 'example.com'],
        ];
    }
}
