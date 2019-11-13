<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class PasswordToken extends AbstractToken
{
    /**
     * @var string
     */
    private $password;

    public function __construct(string $password)
    {
        parent::__construct();
        $this->password = $password;
    }

    public function getCredentials()
    {
        return $this->getPassword();
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
