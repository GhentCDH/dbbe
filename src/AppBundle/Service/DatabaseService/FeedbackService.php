<?php

namespace AppBundle\Service\DatabaseService;

use Doctrine\DBAL\Connection;

class FeedbackService extends DatabaseService
{
    public function insertFeedback(string $url, string $email, string $message): int
    {
        return $this->conn->executeUpdate(
            'INSERT INTO logic.feedback (url, email, message)
            values (?, ?, ?)',
            [
                $url,
                $email,
                $message,
            ]
        );
    }
}
