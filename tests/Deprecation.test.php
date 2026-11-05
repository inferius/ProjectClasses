<?php

use API\Utils\Deprecation;
use PHPUnit\Framework\TestCase;

final class DeprecationTest extends TestCase {
    /** @var string[] */
    private $messages = [];

    protected function setUp(): void {
        Deprecation::reset();
        $this->messages = [];
        set_error_handler(function ($severity, $message) {
            if ($severity === E_USER_DEPRECATED) $this->messages[] = $message;
            return true;
        });
    }

    protected function tearDown(): void {
        restore_error_handler();
        Deprecation::reset();
    }

    public function testSameDeprecationIsReportedOncePerRequest(): void {
        Deprecation::trigger("A::b() - pouzijte c()");
        Deprecation::trigger("A::b() - pouzijte c()");
        Deprecation::trigger("D::e()");

        $this->assertCount(2, $this->messages);
        $this->assertStringContainsString("project-classes: A::b() - pouzijte c()", $this->messages[0]);
        $this->assertStringContainsString("UPGRADE-2.0.md", $this->messages[0]);
    }

    public function testDeprecatedAliasStillWorks(): void {
        $token = (new \API\PlainUrl("/novinky/clanek/", true))->last();
        $this->assertSame($token->slashAtEnd(), $token->slash_at_end());
        $this->assertCount(1, $this->messages);
        $this->assertStringContainsString("slash_at_end", $this->messages[0]);
    }
}
