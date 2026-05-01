<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Models\User;
use PDO;
use PDOStatement;
use RuntimeException;
use Tests\TestCase;

class UserTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createMock(PDO::class);
    }

    public function testCreateReturnsNewUserId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmt);
        $this->pdo->method('lastInsertId')->willReturn('7');

        $user = new User($this->pdo);
        $id = $user->create('test@example.com', 'password123');

        $this->assertSame(7, $id);
    }

    public function testCreateThrowsOnDbError(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willThrowException(
            new \PDOException('Duplicate entry')
        );
        $this->pdo->method('prepare')->willReturn($stmt);

        $this->expectException(RuntimeException::class);

        (new User($this->pdo))->create('dup@example.com', 'pass');
    }

    public function testFindByEmailReturnsRowWhenFound(): void
    {
        $expected = ['id' => 3, 'email' => 'a@b.com', 'password_hash' => 'hash'];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($expected);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = (new User($this->pdo))->findByEmail('a@b.com');

        $this->assertSame($expected, $result);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->assertNull((new User($this->pdo))->findByEmail('nobody@x.com'));
    }

    public function testFindByIdReturnsRowWhenFound(): void
    {
        $expected = ['id' => 1, 'email' => 'x@y.com'];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($expected);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->assertSame($expected, (new User($this->pdo))->findById(1));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->assertNull((new User($this->pdo))->findById(99));
    }

    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        $hash = password_hash('secret123', PASSWORD_BCRYPT);
        $user = ['password_hash' => $hash];

        $this->assertTrue((new User($this->pdo))->verifyPassword($user, 'secret123'));
    }

    public function testVerifyPasswordReturnsFalseForWrongPassword(): void
    {
        $hash = password_hash('secret123', PASSWORD_BCRYPT);
        $user = ['password_hash' => $hash];

        $this->assertFalse((new User($this->pdo))->verifyPassword($user, 'wrong'));
    }

    public function testEmailExistsReturnsTrueWhenFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn('1');

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->assertTrue((new User($this->pdo))->emailExists('exists@x.com'));
    }

    public function testEmailExistsReturnsFalseWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(false);

        $this->pdo->method('prepare')->willReturn($stmt);

        $this->assertFalse((new User($this->pdo))->emailExists('new@x.com'));
    }
}
