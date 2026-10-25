<?php
declare(strict_types=1);
namespace TermTrail\services;

final class SuggestionException extends \RuntimeException
{
    public function __construct(string $message, public int $status = 422, public array $errors = []) { parent::__construct($message); }
}
