<?php

config_set('modules.auth', [
    'max_attempts'  => 5,
    'decay_seconds' => 60,
    'token_expiry'  => 60 * 24 * 7, // 7 days in minutes
]);
