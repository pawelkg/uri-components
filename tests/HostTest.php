<?php

namespace LeagueTest\Uri\Components;

use ArrayIterator;
use League\Uri\Components\Exception;
use League\Uri\Components\Host;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group host
 * @coversDefaultClass \League\Uri\Components\Host
 */
class HostTest extends TestCase
{
    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        $host = new Host('uri.thephpleague.com');
        $this->assertInternalType('array', $host->__debugInfo());
    }

    /**
     * @covers ::__set_state
     */
    public function testSetState()
    {
        $host = new Host('uri.thephpleague.com');
        $generateHost = eval('return '.var_export($host, true).';');
        $this->assertEquals($host, $generateHost);
    }

    /**
     * @covers ::getIterator
     */
    public function testIterator()
    {
        $host = new Host('uri.thephpleague.com');
        $this->assertEquals(['com', 'thephpleague', 'uri'], iterator_to_array($host));
    }

    /**
     * @covers ::__construct
     * @covers ::withContent
     * @covers ::parse
     * @covers ::isValidDomain
     * @covers ::isValidIpv6Hostname
     */
    public function testWithContent()
    {
        $host = new Host('uri.thephpleague.com');
        $this->assertSame($host, $host->withContent('uri.thephpleague.com'));
        $this->assertSame($host, $host->withContent($host));
        $this->assertNotSame($host, $host->withContent('csv.thephpleague.com'));
    }

    /**
     * Test valid Host.
     * @param string|null $host
     * @param bool        $isDomain
     * @param bool        $isIp
     * @param bool        $isIpv4
     * @param bool        $isIpv6
     * @param bool        $isIpFuture
     * @param string|null $ipVersion
     * @param string      $uri
     * @param string      $ip
     * @param string      $iri
     * @dataProvider validHostProvider
     * @covers ::__construct
     * @covers ::parse
     * @covers ::isValidDomain
     * @covers ::isValidIpv6Hostname
     * @covers ::isIp
     * @covers ::isIpv4
     * @covers ::isIpv6
     * @covers ::isIpFuture
     * @covers ::isDomain
     * @covers ::getIp
     * @covers ::getContent
     * @covers ::getUriComponent
     * @covers ::getIpVersion
     */
    public function testValidHost($host, $isDomain, $isIp, $isIpv4, $isIpv6, $isIpFuture, $ipVersion, $uri, $ip, $iri)
    {
        $host = new Host($host);
        $this->assertSame($isDomain, $host->isDomain());
        $this->assertSame($isIp, $host->isIp());
        $this->assertSame($isIpv4, $host->isIpv4());
        $this->assertSame($isIpv6, $host->isIpv6());
        $this->assertSame($isIpFuture, $host->isIpFuture());
        $this->assertSame($uri, $host->getUriComponent());
        $this->assertSame($ip, $host->getIp());
        $this->assertSame($iri, $host->getContent(Host::RFC3987_ENCODING));
        $this->assertSame($ipVersion, $host->getIpVersion());
    }

    public function validHostProvider()
    {
        return [
            'ipv4' => [
                Host::createFromIp('127.0.0.1'),
                false,
                true,
                true,
                false,
                false,
                '4',
                '127.0.0.1',
                '127.0.0.1',
                '127.0.0.1',
                '127.0.0.1',
                false,
                true,
                true,
                false,
                false,
                '4',
                '127.0.0.1',
                '127.0.0.1',
                '127.0.0.1',
            ],
            'ipv6' => [
                '[::1]',
                false,
                true,
                false,
                true,
                false,
                '6',
                '[::1]',
                '::1',
                '[::1]',
            ],
            'scoped ipv6' => [
                '[fe80:1234::%251]',
                false,
                true,
                false,
                true,
                false,
                '6',
                '[fe80:1234::%251]',
                'fe80:1234::%1',
                '[fe80:1234::%251]',
            ],
            'ipfuture' => [
                '[v1.ZZ.ZZ]',
                false,
                true,
                false,
                false,
                true,
                '1',
                '[v1.ZZ.ZZ]',
                'ZZ.ZZ',
                '[v1.ZZ.ZZ]',
            ],
            'normalized' => [
                'Master.EXAMPLE.cOm',
                true,
                false,
                false,
                false,
                false,
                null,
                'master.example.com',
                null,
                'master.example.com',
            ],
            'empty string' => [
                '',
                false,
                false,
                false,
                false,
                false,
                null,
                '',
                null,
                '',
            ],
            'null' => [
                null,
                false,
                false,
                false,
                false,
                false,
                null,
                '',
                null,
                null,
            ],
            'dot ending' => [
                'example.com.',
                true,
                false,
                false,
                false,
                false,
                null,
                'example.com.',
                null,
                'example.com.',
            ],
            'partial numeric' => [
                '23.42c.two',
                true,
                false,
                false,
                false,
                false,
                null,
                '23.42c.two',
                null,
                '23.42c.two',
            ],
            'all numeric' => [
                '98.3.2',
                true,
                false,
                false,
                false,
                false,
                null,
                '98.3.2',
                null,
                '98.3.2',
            ],
            'mix IP format with host label' => [
                'toto.127.0.0.1',
                true,
                false,
                false,
                false,
                false,
                null,
                'toto.127.0.0.1',
                null,
                'toto.127.0.0.1',
            ],
            'idn support' => [
                'مثال.إختبار',
                true,
                false,
                false,
                false,
                false,
                null,
                'xn--mgbh0fb.xn--kgbechtv',
                null,
                'مثال.إختبار',
            ],
            'IRI support' => [
                'xn--mgbh0fb.xn--kgbechtv',
                true,
                false,
                false,
                false,
                false,
                null,
                'xn--mgbh0fb.xn--kgbechtv',
                null,
                'مثال.إختبار',
            ],
            'Registered Name' => [
                'test..example.com',
                false,
                false,
                false,
                false,
                false,
                null,
                'test..example.com',
                null,
                'test..example.com',
            ],
        ];
    }

    /**
     * @param string $invalid
     * @dataProvider invalidHostProvider
     * @covers ::__construct
     * @covers ::parse
     * @covers ::isValidDomain
     * @covers ::isValidIpv6Hostname
     */
    public function testInvalidHost($invalid)
    {
        $this->expectException(Exception::class);
        new Host($invalid);
    }

    public function invalidHostProvider()
    {
        return [
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
        ];
    }

    public function testTypeErrorOnHostConstruction()
    {
        $this->expectException(TypeError::class);
        new Host(date_create());
    }

    /**
     * @covers ::getContent
     */
    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new Host('host'))->getContent(-1);
    }

    /**
     * @param string $raw
     * @param bool   $expected
     * @dataProvider isAbsoluteProvider
     * @covers ::isAbsolute
     */
    public function testIsAbsolute($raw, $expected)
    {
        $this->assertSame($expected, (new Host($raw))->isAbsolute());
    }

    public function isAbsoluteProvider()
    {
        return [
            ['127.0.0.1', false],
            ['example.com.', true],
            ['example.com', false],
        ];
    }

    /**
     * Test Punycode support.
     *
     * @param string $unicode Unicode Hostname
     * @param string $ascii   Ascii Hostname
     * @dataProvider hostnamesProvider
     * @covers ::getContent
     */
    public function testValidUnicodeHost($unicode, $ascii)
    {
        $host = new Host($unicode);
        $this->assertSame($ascii, $host->getContent(Host::RFC3986_ENCODING));
        $this->assertSame($unicode, $host->getContent(Host::RFC3987_ENCODING));
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
            ['[::1]', '[::1]'],
            ['127.0.0.1', '127.0.0.1'],
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
        $this->assertCount($nblabels, new Host($host));
    }

    public function countableProvider()
    {
        return [
            'ip' => ['127.0.0.1', 1, ['127.0.0.1']],
            'string' => ['secure.example.com', 3, ['com', 'example', 'secure']],
            'numeric' => ['92.56.8', 3, ['8', '56', '92']],
            'null' => [null, 0, []],
            'empty string' => ['', 1, ['']],
        ];
    }

    /**
     * @param mixed  $input
     * @param int    $is_absolute
     * @param string $expected
     * @covers ::createFromLabels
     * @covers ::__toString
     *
     * @dataProvider createFromLabelsValid
     */
    public function testCreateFromLabels($input, $is_absolute, $expected)
    {
        $this->assertSame($expected, (string) Host::createFromLabels($input, $is_absolute));
    }

    public function createFromLabelsValid()
    {
        return [
            'array' => [['com', 'example', 'www'], Host::IS_RELATIVE, 'www.example.com'],
            'iterator' => [new ArrayIterator(['com', 'example', 'www']), Host::IS_RELATIVE, 'www.example.com'],
            'ip 1' => [[127, 0, 0, 1], Host::IS_RELATIVE, '1.0.0.127'],
            'ip 2' => [['127.0', '0.1'], Host::IS_RELATIVE, '0.1.127.0'],
            'ip 3' => [['127.0.0.1'], Host::IS_RELATIVE, '127.0.0.1'],
            'FQDN' => [['com', 'example', 'www'], Host::IS_ABSOLUTE, 'www.example.com.'],
            'empty' => [[''], Host::IS_ABSOLUTE, ''],
            'null' => [[], Host::IS_ABSOLUTE, ''],
            'another host object' => [new Host('example.com'), Host::IS_ABSOLUTE, 'example.com.'],
        ];
    }

    /**
     * @param mixed $input
     * @param int   $is_absolute
     * @dataProvider createFromLabelsInvalid
     * @covers ::createFromLabels
     */
    public function testcreateFromLabelsFailed($input, $is_absolute)
    {
        $this->expectException(Exception::class);
        Host::createFromLabels($input, $is_absolute);
    }

    public function createFromLabelsInvalid()
    {
        return [
            'ipv6 FQDN' => [['::1'], Host::IS_ABSOLUTE],
            'unknown flag' => [['all', 'is', 'good'], 23],
        ];
    }

    /**
     * @covers ::createFromLabels
     */
    public function testcreateFromLabelsFailedOnTypeError()
    {
        $this->expectException(TypeError::class);
        Host::createFromLabels(date_create(), Host::IS_RELATIVE);
    }

    /**
     * @dataProvider createFromIpValid
     * @param string $input
     * @param string $version
     * @param string $expected
     * @covers ::createFromIp
     */
    public function testCreateFromIp($input, $version, $expected)
    {
        $this->assertSame($expected, (string) Host::createFromIp($input, $version));
    }

    public function createFromIpValid()
    {
        return [
            'ipv4' => ['127.0.0.1', '', '127.0.0.1'],
            'ipv4 does not care about the version string' => ['127.0.0.1', 'foo', '127.0.0.1'],
            'ipv6' => ['::1', '', '[::1]'],
            'ipv6 does not care about the version string' => ['::1', 'bar', '[::1]'],
            'ipv6 with scope' => ['fe80:1234::%1', 'foo', '[fe80:1234::%251]'],
            'valid IpFuture' => ['csucj.$&+;::', 'AF', '[vAF.csucj.$&+;::]'],
        ];
    }

    /**
     * @dataProvider createFromIpFailed
     * @param string $input
     * @covers ::createFromIp
     */
    public function testCreateFromIpFailed($input)
    {
        $this->expectException(Exception::class);
        Host::createFromIp($input);
    }

    public function createFromIpFailed()
    {
        return [
            'false ipv4' => ['127.0.0'],
            'hostname' => ['example.com'],
            'false ipfuture' => ['vAF.csucj.$&+;:/:'],
        ];
    }

    /**
     * @covers ::getLabel
     */
    public function testGetLabel()
    {
        $host = new Host('master.example.com');
        $this->assertSame('com', $host->getLabel(0));
        $this->assertSame('example', $host->getLabel(1));
        $this->assertSame('master', $host->getLabel(-1));
        $this->assertNull($host->getLabel(23));
    }

    /**
     * @covers ::keys
     */
    public function testOffsets()
    {
        $host = new Host('master.example.com');
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
        $this->assertSame($res, (string) (new Host($host))->withoutLabel($without));
    }

    public function withoutProvider()
    {
        return [
            //'remove unknown label' => ['secure.example.com', 34, 'secure.example.com'],
            'remove one string label' => ['secure.example.com', 0, 'secure.example'],
            'remove one string label negative offset' => ['secure.example.com', -1, 'example.com'],
            'remove IP based label' => ['127.0.0.1', 0, ''],
            'remove simple label' => ['localhost', -1, ''],
        ];
    }

    /**
     * @covers ::withoutLabel
     */
    public function testWithoutTriggersException()
    {
        $this->expectException(Exception::class);
        (new Host('bébé.be'))->withoutLabel(-23);
    }

    /**
     * @param string $host
     * @param string $expected
     * @dataProvider withoutZoneIdentifierProvider
     * @covers ::withoutZoneIdentifier
     */
    public function testWithoutZoneIdentifier($host, $expected)
    {
        $this->assertSame($expected, (string) (new Host($host))->withoutZoneIdentifier());
    }

    public function withoutZoneIdentifierProvider()
    {
        return [
            'hostname host' => ['example.com', 'example.com'],
            'ipv4 host' => ['127.0.0.1', '127.0.0.1'],
            'ipv6 host' => ['[::1]', '[::1]'],
            'ipv6 scoped (1)' => ['[fe80::%251]', '[fe80::]'],
            'ipv6 scoped (2)' => ['[fe80::%1]', '[fe80::]'],
        ];
    }

    /**
     * @param string $host
     * @param bool   $expected
     * @dataProvider hasZoneIdentifierProvider
     * @covers ::hasZoneIdentifier
     */
    public function testHasZoneIdentifier($host, $expected)
    {
        $this->assertSame($expected, (new Host($host))->hasZoneIdentifier());
    }

    public function hasZoneIdentifierProvider()
    {
        return [
            ['127.0.0.1', false],
            ['www.example.com', false],
            ['[::1]', false],
            ['[fe80::%251]', true],
        ];
    }

    /**
     * @param string $raw
     * @param string $input
     * @param int    $offset
     * @param string $expected
     * @dataProvider replaceValid
     * @covers ::withLabel
     */
    public function testReplace($raw, $input, $offset, $expected)
    {
        $this->assertSame($expected, (string) (new Host($raw))->withLabel($offset, $input));
    }

    public function replaceValid()
    {
        return [
            ['master.example.com', 'shop', 2, 'shop.example.com'],
            ['master.example.com', 'master', 2, 'master.example.com'],
            ['secure.example.com', '127.0.0.1', 0, 'secure.example.127.0.0.1'],
            ['master.example.com', 'shop', -2, 'master.shop.com'],
            ['master.example.com', 'shop', -1, 'shop.example.com'],
            ['foo', 'bar', -1, 'bar'],
        ];
    }

    /**
     * @covers ::withLabel
     * @covers ::parse
     * @dataProvider replaceInvalid
     * @param mixed $host
     * @param mixed $key
     * @param mixed $label
     */
    public function testReplaceIpMustFailed($host, $key, $label)
    {
        $this->expectException(Exception::class);
        (new Host($host))->withLabel($key, $label);
    }

    public function replaceInvalid()
    {
        return [
            ['secure.example.com', 2, '[::1]'],
            ['toto', 2, 'bar'],
        ];
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
        $host = new Host($host);
        $this->assertSame($expected_with_root, (string) $host->withRootLabel());
        $this->assertSame($expected_without_root, (string) $host->withoutRootLabel());
    }

    public function rootProvider()
    {
        return [
            ['example.com', 'example.com.', 'example.com'],
            ['example.com.', 'example.com.', 'example.com'],
            ['127.0.0.1', '127.0.0.1', '127.0.0.1'],
        ];
    }
}
