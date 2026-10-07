<?php

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testToujoursRoleUser(): void
    {
        $this->assertSame(['ROLE_USER'], (new User())->getRoles());
        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], (new User())->setRoles(['ROLE_ADMIN', 'ROLE_USER'])->getRoles());
    }

    public function testIdentifiant(): void
    {
        $user = (new User())->setUsername('marie')->setPassword('hash');

        $this->assertSame('marie', $user->getUsername());
        $this->assertSame('hash', $user->getPassword());
    }
}
