<?php
namespace app\services;

abstract class BaseAuthService extends BaseService
{
    abstract public function authorize(string $username): bool;
}
