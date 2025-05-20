<?php

require __DIR__ . '/../config/teams.php';

const ROLE_LEAD = 1;
const ROLE_DEV = 2;
const ROLE_TEST = 3;

return [
    31 => [
        'telegram' => 'katunsev',
        'team' => TEAM_CRM,
        'role' => ROLE_LEAD,
        'chat_id' => 207223883
    ],
];