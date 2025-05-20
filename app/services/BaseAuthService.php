<?php
namespace App\Services;

abstract class BaseAuthService extends BaseService
{
    abstract public function authorize(string $username): bool;
}
