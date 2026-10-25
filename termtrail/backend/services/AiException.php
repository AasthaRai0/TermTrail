<?php
declare(strict_types=1);
namespace TermTrail\services;

/** Error whose message is safe to show to users (never contains keys or contract text). */
final class AiException extends \RuntimeException {}
