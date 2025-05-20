<?php
namespace app\repositories;

use app\models\User;

class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected string $modelClass = User::class;
}
