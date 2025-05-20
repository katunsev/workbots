<?php
namespace App\Repositories;

use App\Models\User;

class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected string $modelClass = User::class;
}
