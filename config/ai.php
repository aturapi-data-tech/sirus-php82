<?php

return [
    'api_url' => env('AI_API_URL', env('OPENCLAW_API_URL', 'http://127.0.0.1:18789') . '/v1/chat/completions'),
    'api_key' => env('AI_API_KEY', env('OPENCLAW_API_KEY', '')),
    'model' => env('AI_MODEL', 'openclaw/default'),
    'timeout' => (int) env('AI_TIMEOUT', 60),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 2000),
];
