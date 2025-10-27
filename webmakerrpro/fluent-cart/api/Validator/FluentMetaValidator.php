<?php

namespace FluentCart\Api\Validator;

class FluentMetaValidator extends Validation
{
    public static function rules(): array
    {
        return [
            'object_id' => 'integer|min:1',
            'object_type' => 'sanitizeText|maxLength:50',
            'meta_key' => 'sanitizeText|maxLength:192',
        ];
    }
}

