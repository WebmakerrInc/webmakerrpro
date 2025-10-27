<?php

set_error_handler(function($errno, $msg, $fn, $ln) use ($file) {
    
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $levels = [
        E_ALL               => "E_ALL",
        E_ERROR             => "E_ERROR",
        E_WARNING           => "E_WARNING",
        E_PARSE             => "E_PARSE",
        E_NOTICE            => "E_NOTICE",
        E_CORE_ERROR        => "E_CORE_ERROR",
        E_CORE_WARNING      => "E_CORE_WARNING",
        E_COMPILE_ERROR     => "E_COMPILE_ERROR",
        E_COMPILE_WARNING   => "E_COMPILE_WARNING",
        E_USER_ERROR        => "E_USER_ERROR",
        E_USER_WARNING      => "E_USER_WARNING",
        E_USER_NOTICE       => "E_USER_NOTICE",
        E_STRICT            => "E_STRICT",
        E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
        E_DEPRECATED        => "E_DEPRECATED",
        E_USER_DEPRECATED   => "E_USER_DEPRECATED"
    ];

    $levelName = $levels[$errno] ?? "UNKNOWN ERROR LEVEL";

    // Improved debugging information
    if (strpos($fn, dirname($file) . '/') !== false) {
        $debugInfo = [
            'Error Level' => $levelName,
            'Message' => $msg,
            'File' => $fn,
            'Line' => $ln,
            'Backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];

        throw new ErrorException(sprintf(
            '%s - %s in %s at line %s. Debug Info: %s',
            $levelName,
            $msg,
            $fn,
            $ln,
            print_r($debugInfo, true)
        ), 500);
    }

    return false;
});
