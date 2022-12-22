<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Error;

final class PlainUrlTest extends TestCase {

    /** @var \API\PlainUrl $pu */
    private $pu;
    private $prepared = false;

    public function prepareData() {


        //$this->pu = new \API\PlainUrl("/en/9.5/writing-tests-for-phpunit/testurl");
        @\API\PlainUrl::registerTokenTypeChecker("language", function ($token, $position, $no_grp_position) {
            $accept_language = [ "en", "cs", "cs_CZ", "en_US" ];
            if ($position === 0) {
                return in_array($token, $accept_language);
            }
            else {
                return false;
            }
        });

        @\API\PlainUrl::registerTokenTypeChecker("token", function ($token, $position, $no_grp_position, &$custom_data) {
            if (\Nette\Utils\Strings::startsWith($token, "token-")) {
                $custom_data = [
                    "token" => \Nette\Utils\Strings::after($token, "-")
                ];

                return true;
            }

            return false;
        });

        @\API\PlainUrl::registerTokenTypeChecker("version", function ($token, $position, $no_grp_position, &$custom_data) {
            $version_regex = "/^v?(?<main>[0-9]+)(?:\.(?<sub>[0-9]+))?$/mi";
            if ($no_grp_position === 0 && preg_match($version_regex, $token, $matches)) {

                $custom_data = [
                    "version" => $matches["main"],
                    "main" => $matches["main"],
                ];

                if (!empty($matches["sub"])) {
                    $custom_data["sub"] = $matches["sub"];
                    $custom_data["version"] .= ".{$custom_data["sub"]}";
                }
                else $custom_data["version"] .= ".0";

                return true;

            }
            else {
                return false;
            }
        });
    }

    public function dataProvider(): array {
        return [
            "has lang groups-1" => [ "language", "/en/9/writing-tests-for-phpunit/testurl", true ],
            "has lang groups-2" => [ "language", "/cs/9/writing-tests-for-phpunit/testurl", true ],
            "has lang groups-3" => [ "language", "/cs_CZ/9/writing-tests-for-phpunit/testurl", true ],
            "has lang groups-4" => [ "language", "/fr/9/writing-tests-for-phpunit/testurl", false ],

            "has version groups-5" => [ "version", "/en/9/writing-tests-for-phpunit/testurl", true ],
            "has version groups-6" => [ "version", "/en/9.555/writing-tests-for-phpunit/testurl", true ],
            "has version groups-7" => [ "version", "/en/559.555/writing-tests-for-phpunit/testurl", true ],
            "has version groups-8" => [ "version", "/en/559.555./writing-tests-for-phpunit/testurl", false ],
            "has version groups-9" => [ "version", "/en/.555./writing-tests-for-phpunit/testurl", false ],
            "has version groups-10" => [ "version", "/en/9.9.1/writing-tests-for-phpunit/testurl", false ],
            "has version groups-11" => [ "version", "/en/v9.9/writing-tests-for-phpunit/testurl", true ],

            "has !version groups-11" => [ "!version", "/en/9/writing-tests-for-phpunit/testurl", false ],
            "has !version groups-12" => [ "!version", "/en/writing-tests-for-phpunit/testurl", true ],

            "has lang groups-13" => [ "!language", "/en/9.5/writing-tests-for-phpunit/testurl", false ],
            "has lang groups-14" => [ ["language", "token", "version"], "/en/9.5/writing-tests-for-phpunit/testurl", false ],
            "has lang groups-15" => [ ["language", "token", "!version"], "/en/9.5/writing-tests-for-phpunit/testurl", false ],
            "has lang groups-16" => [ ["language", "token"], "/en/9.5/writing-tests-for-phpunit/testurl", false ],
            "has token groups" => [ "token", "/en/9.5/writing-tests-for-phpunit/token-123456987", true ],
            "has multigroup groups" => [ ["token", "language" ], "/en/9.5/writing-tests-for-phpunit/token-123456987", true ],
            "has no token groups" => [ "!token", "/en/9.5/writing-tests-for-phpunit/token", true ],
            "has multigroup not groups-1" => [ ["!token", "language" ], "/en/9.5/writing-tests-for-phpunit/token-123456987", false ],
            "has multigroup not groups-2" => [ ["!token", "language" ], "/en/9.5/writing-tests-for-phpunit/token", true ],
        ];
    }

    public function dataFirstProvider(): array {
        return [
            "has lang groups" => [ [ "language" ], "/en/9.5/writing-tests-for-phpunit/testurl", "en" ],
            "has token groups" => [ [ "token" ], "/en/9.5/writing-tests-for-phpunit/token-123456987", "token-123456987" ],
            "has no token groups" => [ [ "!token" ], "/en/9.5/writing-tests-for-phpunit/token", "en" ],
        ];
    }

    public function dataUrlProvider(): array {
        return [
            "has lang groups" => [ [ "language" ], "/en/9.5/writing-tests-for-phpunit/testurl", "/en/" ],
            "has no lang groups" => [ [ "!language" ], "/en/9.5/writing-tests-for-phpunit/testurl", "/9.5/writing-tests-for-phpunit/testurl/" ],
            "has token groups" => [ [ "token" ], "/en/9.5/writing-tests-for-phpunit/token-123456987", "/token-123456987/" ],
            "has no token groups-1" => [ [ "!token" ], "/en/9.5/writing-tests-for-phpunit/token-123456987", "/en/9.5/writing-tests-for-phpunit/" ],
            "has no token groups-2" => [ [ "!token" ], "/en/9.5/writing-tests-for-phpunit/token", "/en/9.5/writing-tests-for-phpunit/token/" ],
            "default|empty_array" => [ [], "/en/9.5/writing-tests-for-phpunit/token", "/en/9.5/writing-tests-for-phpunit/token/" ],
            "default|null" => [ null, "/en/9.5/writing-tests-for-phpunit/token", "/en/9.5/writing-tests-for-phpunit/token/" ],
            "default|false" => [ false, "/en/9.5/writing-tests-for-phpunit/token", "/en/9.5/writing-tests-for-phpunit/token/" ],
            "default|empty_string" => [ "", "/en/9.5/writing-tests-for-phpunit/token", "/en/9.5/writing-tests-for-phpunit/token/" ],
        ];
    }

    public function dataCustomDataProvider(): array {
        return [
            "has token groups" => [ [ "token" ], "/en/9.5/writing-tests-for-phpunit/token-123456987", "token", "123456987" ],

            "has version groups-1" => [ "version", "/en/9.9/writing-tests-for-phpunit/testurl", "version", "9.9" ],
            "has version groups-2" => [ "version", "/en/v9.9/writing-tests-for-phpunit/testurl", "version", "9.9" ],
            "has version groups-3" => [ "version", "/en/v9/writing-tests-for-phpunit/testurl", "version", "9.0" ],
            "has version groups-4" => [ "version", "/en/v9.0/writing-tests-for-phpunit/testurl", "version", "9.0" ],
            "has version groups-5" => [ "version", "/en/9.0/writing-tests-for-phpunit/testurl", "version", "9.0" ],
            "has version groups-6" => [ "version", "/en/9/writing-tests-for-phpunit/testurl", "version", "9.0" ],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testContainsGroup($group, string $url, bool $expected) {
        $this->prepareData();
        $pu = new \API\PlainUrl($url);
        $this->assertSame($expected, $pu->containsGroups($group));
    }

    /**
     * @dataProvider dataFirstProvider
     */
    public function testFirst($groups, string $url, string $expected) {
        $this->prepareData();
        $pu = new \API\PlainUrl($url);

        $this->assertSame($expected, $pu->first($groups)->token());
    }

    /**
     * @dataProvider dataUrlProvider
     */
    public function testUrl($groups, string $url, string $expected) {
        $this->prepareData();
        $pu = new \API\PlainUrl($url);

        $this->assertSame($expected, $pu->url($groups));
    }

    /**
     * @dataProvider dataCustomDataProvider
     * @depends testFirst
     */
    public function testCustomData($groups, string $url, string $custom_key, string $expected) {
        $this->prepareData();
        $pu = new \API\PlainUrl($url);

        $this->assertSame($expected, $pu->first($groups)->getCustomDataItem($custom_key));
    }


}