<?php

namespace LeagueTest\Uri\Components;

use ArrayIterator;
use League\Uri\Components\Exception;
use League\Uri\Components\Host;
use League\Uri\PublicSuffix\Cache;
use League\Uri\PublicSuffix\CurlHttpClient;
use League\Uri\PublicSuffix\ICANNSectionManager;
use LogicException;
use PHPUnit\Framework\TestCase;
use Traversable;

/**
 * @group host
 */
final class HostTest extends TestCase
{
    public function testDebugInfo()
    {
        $host = new Host('uri.thephpleague.com');
        self::assertInternalType('array', $host->__debugInfo());
    }

    public function testSetState()
    {
        $host = new Host('uri.thephpleague.com');
        self::assertSame('thephpleague.com', $host->getRegisterableDomain());
        $generateHost = eval('return '.var_export($host, true).';');
        self::assertEquals($host, $generateHost);
    }

    public function testDefined()
    {
        $component = new Host('yolo');
        self::assertFalse($component->isNull());
        self::assertTrue($component->withContent(null)->isNull());
        self::assertTrue($component->withContent(null)->isEmpty());
        self::assertTrue($component->withContent('')->isEmpty());
    }

    public function testWithContent()
    {
        $host = new Host('uri.thephpleague.com');
        $alt_host = $host->withContent('uri.thephpleague.com');
        self::assertSame($alt_host, $host);
    }

    public function testWithDomainResolver()
    {
        $resolver = (new ICANNSectionManager(new Cache(), new CurlHttpClient()))->getRules();
        $host = new Host('uri.thephpleague.com');
        $newHost = $host->withDomainResolver($resolver);
        self::assertNotEquals($newHost, $host);
    }

    public function testWithDomainResolverOnSameResolver()
    {
        $host = new Host('uri.thephpleague.com');
        $newHost = $host->withDomainResolver();
        self::assertInstanceOf(Host::class, $host);
    }

    /**
     * Test valid Host
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
     */
    public function testValidHost($host, $isDomain, $isIp, $isIpv4, $isIpv6, $isIpFuture, $ipVersion, $uri, $ip, $iri)
    {
        $host = new Host($host);
        self::assertSame($isDomain, $host->isDomain());
        self::assertSame($isIp, $host->isIp());
        self::assertSame($isIpv4, $host->isIpv4());
        self::assertSame($isIpv6, $host->isIpv6());
        self::assertSame($isIpFuture, $host->isIpFuture());
        self::assertSame($uri, $host->getUriComponent());
        self::assertSame($ip, $host->getIp());
        self::assertSame($iri, $host->getContent(Host::RFC3987_ENCODING));
        self::assertSame($ipVersion, $host->getIpVersion());
    }

    public function validHostProvider()
    {
        return [
            'ipv4' => [
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
     */
    public function testInvalidHost($invalid)
    {
        $this->expectException(Exception::class);
        new Host($invalid);
    }

    public function testInvalidEncodingTypeThrowException()
    {
        $this->expectException(Exception::class);
        (new Host('host'))->getContent(-1);
    }

    public function invalidHostProvider()
    {
        //$longlabel = implode('', array_fill(0, 12, 'banana'));

        return [
            //'dot in front' => ['.example.com'], valid registered name
            //'hyphen suffix' => ['host.com-'],   valid registered name
            //'multiple dot' => ['.......'],      valid registered name
            //'one dot' => ['.'],                 valid registered name
            'empty label' => ['tot.    .coucou.com'],
            'space in the label' => ['re view'],
            //'underscore in label' => ['_bad.host.com'],                   valid registered name
            //'label too long' => [$longlabel.'.secure.example.com'],       valid registered name
            //'too many labels' => [implode('.', array_fill(0, 128, 'a'))], valid registered name
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

    /**
     * @param string $raw
     * @param bool   $expected
     * @dataProvider isAbsoluteProvider
     */
    public function testIsAbsolute($raw, $expected)
    {
        self::assertSame($expected, (new Host($raw))->isAbsolute());
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
     * Test Punycode support
     *
     * @param string $unicode Unicode Hostname
     * @param string $ascii   Ascii Hostname
     * @dataProvider hostnamesProvider
     */
    public function testValidUnicodeHost($unicode, $ascii)
    {
        $host = new Host($unicode);
        self::assertSame($ascii, $host->getContent(Host::RFC3986_ENCODING));
        self::assertSame($unicode, $host->getContent(Host::RFC3987_ENCODING));
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
     * Test Countable
     *
     * @param string|null $host
     * @param int         $nblabels
     * @param array       $array
     * @dataProvider countableProvider
     */
    public function testCountable($host, $nblabels, $array)
    {
        $obj = new Host($host);
        self::assertCount($nblabels, $obj);
        self::assertSame($array, $obj->getLabels());
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
     * @param array|Traversable $input
     * @param int               $is_absolute
     * @param string            $expected
     * @dataProvider createFromLabelsValid
     */
    public function testCreateFromLabels($input, $is_absolute, $expected)
    {
        self::assertSame($expected, (string) Host::createFromLabels($input, $is_absolute));
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
        ];
    }

    /**
     * @param array $input
     * @param int   $is_absolute
     * @dataProvider createFromLabelsInvalid
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
     * @dataProvider createFromIpValid
     * @param string $input
     * @param string $expected
     */
    public function testCreateFromIp($input, $expected)
    {
        self::assertSame($expected, (string) Host::createFromIp($input));
    }

    public function createFromIpValid()
    {
        return [
            'ipv4' => ['127.0.0.1', '127.0.0.1'],
            'ipv6' => ['::1', '[::1]'],
            'ipv6 with scope' => ['fe80:1234::%1', '[fe80:1234::%251]'],
            'valid IpFuture' => ['vAF.csucj.$&+;::', '[vAF.csucj.$&+;::]'],
        ];
    }

    /**
     * @dataProvider createFromIpFailed
     * @param string $input
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

    public function testGetLabel()
    {
        $host = new Host('master.example.com');
        self::assertSame('com', $host->getLabel(0));
        self::assertSame('example', $host->getLabel(1));
        self::assertSame('master', $host->getLabel(-1));
        self::assertNull($host->getLabel(23));
        self::assertSame('toto', $host->getLabel(23, 'toto'));
    }

    public function testOffsets()
    {
        $host = new Host('master.example.com');
        self::assertSame([0, 1, 2], $host->keys());
        self::assertSame([2], $host->keys('master'));
    }

    /**
     * @param string $host
     * @param array  $without
     * @param string $res
     * @dataProvider withoutProvider
     */
    public function testWithout($host, $without, $res)
    {
        self::assertSame($res, (string) (new Host($host))->withoutLabels($without));
    }

    public function withoutProvider()
    {
        return [
            'remove unknown label' => ['secure.example.com', [34], 'secure.example.com'],
            'remove one string label' => ['secure.example.com', [0], 'secure.example'],
            'remove one string label negative offset' => ['secure.example.com', [-1], 'example.com'],
            'remove IP based label' => ['127.0.0.1', [0], ''],
            'remove silent excessive label index' => ['127.0.0.1', [0, 1] , ''],
            'remove simple label' => ['localhost', [-1], ''],
        ];
    }

    public function testWithoutTriggersException()
    {
        $this->expectException(Exception::class);
        (new Host('bébé.be'))->withoutLabels(['be']);
    }

    /**
     * @param string $host
     * @param string $expected
     * @dataProvider withoutZoneIdentifierProvider
     */
    public function testWithoutZoneIdentifier($host, $expected)
    {
        self::assertSame($expected, (string) (new Host($host))->withoutZoneIdentifier());
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
     */
    public function testHasZoneIdentifier($host, $expected)
    {
        self::assertSame($expected, (new Host($host))->hasZoneIdentifier());
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
     * @param string $prepend
     * @param string $expected
     * @dataProvider validPrepend
     */
    public function testPrepend($raw, $prepend, $expected)
    {
        self::assertSame($expected, (string) (new Host($raw))->prepend($prepend));
    }

    public function validPrepend()
    {
        return [
            ['secure.example.com', 'master', 'master.secure.example.com'],
            ['secure.example.com', 'master.', 'master.secure.example.com'],
            ['secure.example.com.', 'master', 'master.secure.example.com.'],
            ['secure.example.com', '127.0.0.1', '127.0.0.1.secure.example.com'],
            ['example.com', '', 'example.com'],
        ];
    }

    public function testPrependIpFailed()
    {
        $this->expectException(Exception::class);
        (new Host('::1'))->prepend(new Host('foo'));
    }

    /**
     * @param string $raw
     * @param string $append
     * @param string $expected
     * @dataProvider validAppend
     */
    public function testAppend($raw, $append, $expected)
    {
        self::assertSame($expected, (string) (new Host($raw))->append($append));
    }

    public function validAppend()
    {
        return [
            ['secure.example.com', 'master', 'secure.example.com.master'],
            ['secure.example.com', 'master.', 'secure.example.com.master'],
            ['secure.example.com.', 'master', 'secure.example.com.master.'],
            ['127.0.0.1', 'toto', '127.0.0.1.toto'],
            ['example.com', '', 'example.com'],
        ];
    }

    /**
     * @expectedException LogicException
     */
    public function testAppendIpFailed()
    {
        (new Host('[::1]'))->append('foo');
    }

    /**
     * @param string $raw
     * @param string $input
     * @param int    $offset
     * @param string $expected
     * @dataProvider replaceValid
     */
    public function testReplace($raw, $input, $offset, $expected)
    {
        self::assertSame($expected, (new Host($raw))->replaceLabel($offset, $input)->__toString());
    }

    public function replaceValid()
    {
        return [
            ['master.example.com', 'shop', 2, 'shop.example.com'],
            ['master.example.com', 'master', 2, 'master.example.com'],
            ['toto', '[::1]', 23, 'toto'],
            ['127.0.0.1', 'secure.example.com', 2, '127.0.0.1'],
            ['secure.example.com', '127.0.0.1', 0, 'secure.example.127.0.0.1'],
            ['master.example.com', 'shop', -2, 'master.shop.com'],
            ['master.example.com', 'shop', -1, 'shop.example.com'],
            ['foo', 'bar', -1, 'bar'],
        ];
    }

    public function testReplaceIpMustFailed()
    {
        $this->expectException(Exception::class);
        (new Host('secure.example.com'))->replaceLabel(2, '[::1]');
    }

    /**
     * @dataProvider parseDataProvider
     * @param string $host
     * @param string $publicSuffix
     * @param string $registerableDomain
     * @param string $subdomain
     * @param bool   $isValidSuffix
     */
    public function testPublicSuffixListImplementation(
        $host,
        $publicSuffix,
        $registerableDomain,
        $subdomain,
        $isValidSuffix
    ) {
        $host = new Host($host);
        self::assertSame($subdomain, $host->getSubDomain());
        self::assertSame($registerableDomain, $host->getRegisterableDomain());
        self::assertSame($publicSuffix, $host->getPublicSuffix());
        self::assertSame($isValidSuffix, $host->isPublicSuffixValid());
    }

    public function parseDataProvider()
    {
        return [
            ['www.waxaudio.com.au', 'com.au', 'waxaudio.com.au', 'www', true],
            ['giant.yyyy.', 'yyyy', 'giant.yyyy', '', false],
            ['localhost', '', '', '', false],
            ['127.0.0.1', '', '', '', false],
            ['[::1]', '', '', '', false],
            ['مثال.إختبار', 'xn--kgbechtv', 'xn--mgbh0fb.xn--kgbechtv', '', false],
            ['xn--p1ai.ru.', 'ru', 'xn--p1ai.ru', '', true],
        ];
    }

    /**
     * @dataProvider validPublicSuffix
     *
     * @param string $publicsuffix
     * @param string $host
     * @param string $expected
     */
    public function testWithPublicSuffix($publicsuffix, $host, $expected)
    {
        self::assertSame(
            $expected,
            (string) (new Host($host))->withPublicSuffix($publicsuffix)
        );
    }

    public function testWithPublicSuffixOnEmptySource()
    {
        self::assertSame(
            '',
            (string) (new Host(''))->withPublicSuffix('')
        );
    }

    public function testWithPublicSuffixOnInvalidHostName()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The submitted host `.` is invalid');
        self::assertSame(
            '',
            (string) (new Host('.'))->withPublicSuffix('.')
        );
    }

    public function validPublicSuffix()
    {
        return [
            ['fr', 'example.co.uk', 'example.fr'],
            ['fr', 'example.be', 'example.fr'],
            ['bébé', 'example.com', 'example.xn--bb-bjab'],
            ['127.0.0.1', 'example.co.uk', 'example.127.0.0.1'],
            ['fr', 'example.fr', 'example.fr'],
            ['', 'example.fr', 'example'],
        ];
    }

    public function testWithPublicSuffixThrowException()
    {
        $this->expectException(Exception::class);
        (new Host('[::1]'))->withPublicSuffix('example.com');
    }

    /**
     * @dataProvider validRegisterableDomain
     * @param string $newhost
     * @param string $host
     * @param string $expected
     */
    public function testWithRegisterableDomain($newhost, $host, $expected)
    {
        self::assertSame($expected, (string) (new Host($host))->withRegisterableDomain($newhost));
    }

    public function validRegisterableDomain()
    {
        return [
            ['thephpleague.com', 'shop.example.com', 'shop.thephpleague.com'],
            ['thephpleague.com', 'shop.ulb.ac.be', 'shop.thephpleague.com'],
            ['thephpleague.com', 'shop.ulb.ac.be.', 'shop.thephpleague.com.'],
            ['thephpleague.com', '', 'thephpleague.com'],
            ['thephpleague.com', 'shop.thephpleague.com', 'shop.thephpleague.com'],
            ['example.com', '127.0.0.1', '127.0.0.1.example.com'],
            ['', 'www.example.com', 'www'],
        ];
    }

    public function testWithRegisterableDomainThrowException()
    {
        $this->expectException(Exception::class);
        (new Host('[::1]'))->withRegisterableDomain('example.com');
    }

    public function testWithSubDomainThrowExceptionWithAbsoluteRegisterableDomain()
    {
        $this->expectException(Exception::class);
        (new Host('example.com'))->withRegisterableDomain('example.com.');
    }

    /**
     * @dataProvider validSubDomain
     * @param string $new_subdomain
     * @param string $host
     * @param string $expected
     */
    public function testWithSubDomain($new_subdomain, $host, $expected)
    {
        self::assertSame($expected, (string) (new Host($host))->withSubDomain($new_subdomain));
    }

    public function validSubDomain()
    {
        return [
            ['shop', 'master.example.com', 'shop.example.com'],
            ['shop', 'www.ulb.ac.be', 'shop.ulb.ac.be'],
            ['shop', 'ulb.ac.be', 'shop.ulb.ac.be'],
            ['', 'ulb.ac.be.', 'ulb.ac.be.'],
            ['www', 'www.ulb.ac.be', 'www.ulb.ac.be'],
            ['www', '', 'www'],
            ['www', 'example.com.', 'www.example.com.'],
            ['example.com', '127.0.0.1', 'example.com.127.0.0.1'],
            ['', 'www.example.com', 'example.com'],
        ];
    }

    public function testWithSubDomainThrowExceptionWithIPHost()
    {
        $this->expectException(Exception::class);
        (new Host('[::1]'))->withSubDomain('example.com');
    }

    public function testWithSubDomainThrowExceptionWithAbsoluteSubDomain()
    {
        $this->expectException(Exception::class);
        (new Host('example.com'))->withSubDomain('example.com.');
    }

    /**
     * @dataProvider rootProvider
     * @param string $host
     * @param string $expected_with_root
     * @param string $expected_without_root
     */
    public function testWithRooot($host, $expected_with_root, $expected_without_root)
    {
        $host = new Host($host);
        self::assertSame($expected_with_root, (string) $host->withRootLabel());
        self::assertSame($expected_without_root, (string) $host->withoutRootLabel());
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
