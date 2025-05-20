<?php
namespace app\services;

class AuthService extends BaseAuthService
{
    protected array $allowedUsernames;

    public function __construct(array $allowedUsernames)
    {
        $this->allowedUsernames = $allowedUsernames;
    }

    public function authorize(string $username): bool
    {
        return in_array($username, $this->allowedUsernames, true);
    }
}
