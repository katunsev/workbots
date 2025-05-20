<?php
namespace App\repositories;

use app\models\User;

class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected string $modelClass = User::class;
}
