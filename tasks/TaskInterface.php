<?php
declare(strict_types=1);

interface TaskInterface {
    /**
     * Start the task: validate pre-conditions, generate token, log start.
     * Returns ['success'=>bool, 'token'=>string, 'message'=>string, ...]
     */
    public function start(int $userId, int $taskId, string $ip, string $fingerprint): array;

    /**
     * Complete the task: validate token + timing + anti-cheat, award reward.
     * Returns ['success'=>bool, 'points'=>int, 'message'=>string]
     */
    public function complete(int $userId, int $taskId, string $token, array $extra = []): array;

    /**
     * Return the task type identifier string.
     */
    public function getType(): string;
}
