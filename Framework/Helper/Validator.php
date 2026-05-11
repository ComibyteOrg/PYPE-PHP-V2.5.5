<?php
namespace Framework\Helper;

/**
 * Validator — alias for EnhancedValidator for backward compatibility.
 * Use EnhancedValidator directly for the full feature set.
 */
class Validator extends EnhancedValidator
{
    public static function make($data, $rules, $messages = []): self
    {
        return parent::make((array) $data, (array) $rules, (array) $messages);
    }
}
