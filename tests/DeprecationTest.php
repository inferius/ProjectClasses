<?php

use API\Utils\Deprecation;
use PHPUnit\Framework\TestCase;

final class DeprecationTest extends TestCase {
    /** @var string[] */
    private array $messages = [];

    protected function setUp(): void {
        Deprecation::reset();
        $this->messages = [];
        set_error_handler(function ($severity, $message): true {
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
        $this->assertStringContainsString("pristi hlavni verzi", $this->messages[0]);
    }

}
