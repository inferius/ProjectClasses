<?php

use API\Configurator;
use API\Users;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeConnection;

final class UsersTest extends TestCase {
    protected function tearDown(): void {
        Configurator::$connection = null;
    }

    private function user(array $row, array $groups): Users {
        Configurator::$connection = (new FakeConnection())
            ->on("SELECT * FROM users_data WHERE id = ?", fn(): \stdClass => (object)$row)
            ->on("SELECT pg.* FROM perm_groups", fn(): array => $groups);

        return new Users($row["id"]);
    }

    public function testGroupIdsAreIntegers(): void {
        $u = $this->user(
            [ "id" => 7, "name" => "Eva", "is_allowed" => 1, "to_delete" => 0 ],
            [ [ "id" => "3", "text_id" => "editor" ], [ "id" => "5", "text_id" => "developer" ] ]
        );

        $this->assertSame([ 3, 5 ], $u->getGroupIds());
        $this->assertSame([ "editor", "developer" ], $u->getGroups());
    }

    public function testWithoutGroups(): void {
        $u = $this->user([ "id" => 7, "name" => "Eva", "is_allowed" => 1, "to_delete" => 0 ], []);

        $this->assertSame([], $u->getGroupIds());
        $this->assertNull($u->getGroups());
    }

    public function testSuperAdminColumn(): void {
        $admin = $this->user([ "id" => 1, "name" => "A", "is_allowed" => 1, "to_delete" => 0, "is_super_admin" => 1 ], []);
        $this->assertTrue($admin->isSuperAdmin());

        $plain = $this->user([ "id" => 2, "name" => "B", "is_allowed" => 1, "to_delete" => 0, "is_super_admin" => 0 ], []);
        $this->assertFalse($plain->isSuperAdmin());
    }

    public function testSuperAdminWithoutColumnIsFalse(): void {
        // sloupec pridava az migrace modulu opravneni
        $u = $this->user([ "id" => 3, "name" => "C", "is_allowed" => 1, "to_delete" => 0 ], []);
        $this->assertFalse($u->isSuperAdmin());
    }
}
