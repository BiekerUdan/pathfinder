<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 19.01.2019
 * Time: 11:34
 *
 * Refactored to use composition instead of inheritance since upstream
 * GuzzleRetry\GuzzleRetryMiddleware now has a final constructor.
 */

namespace Exodus4D\Pathfinder\ESI\Lib\Middleware;

use Closure;
use GuzzleRetry\GuzzleRetryMiddleware as BaseGuzzleRetryMiddleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleRetryMiddleware {

    /**
     * default for: activate middleware "retry requests"
     */
    const DEFAULT_RETRY_ENABLED                 = true;

    /**
     * default for: retry request count
     */
    const DEFAULT_RETRY_MAX_ATTEMPTS            = 2;

    /**
     * default for: retry multiplier
     */
    const DEFAULT_RETRY_MULTIPLIER              = 0.5;

    /**
     * default for: retry requests "on status"
     * HTTP 429 "Too Many Requests"     (default)
     * HTTP 503 "Service Unavailable"   (default)
     * HTTP 504 "Gateway Timeout"
     */
    const DEFAULT_RETRY_ON_STATUS               = [429, 503, 504];

    /**
     * default for: retry request "on timeout"
     */
    const DEFAULT_RETRY_ON_TIMEOUT              = true;

    /**
     * default for: retry request add "X-Retry-Counter" header
     */
    const DEFAULT_RETRY_EXPOSE_RETRY_HEADER     = false;

    // Custom config options ------------------------------------------------------------------------------------------

    /**
     * default for: log requests that exceed "retryCountMax"
     */
    const DEFAULT_RETRY_LOG_ERROR               = true;

    /**
     * default for: callback function that checks a $request
     * -> can be used to "exclude" some requests from been logged (e.g. on expected downtime)
     */
    const DEFAULT_RETRY_LOGGABLE_CALLBACK       = null;

    /**
     * default for: callback function for logging
     */
    const DEFAULT_RETRY_LOG_CALLBACK            = null;

    /**
     * default for: name for log file
     */
    const DEFAULT_RETRY_LOG_FILE                = 'retry_requests';

    /**
     * default for: log message format
     */
    const DEFAULT_RETRY_LOG_FORMAT              = '[{attempt}/{maxRetry}] RETRY FAILED {method} {target} HTTP/{version} → {code} {phrase}';

    /**
     * Factory method that returns a closure for use with HandlerStack
     * This wraps the upstream GuzzleRetryMiddleware with our custom logging callback
     *
     * @param array $defaultOptions
     * @return Closure
     */
    public static function factory(array $defaultOptions = []): Closure {
        // Merge our defaults with provided options
        $mergedOptions = array_replace([
            'retry_enabled'                         => self::DEFAULT_RETRY_ENABLED,
            'max_retry_attempts'                    => self::DEFAULT_RETRY_MAX_ATTEMPTS,
            'default_retry_multiplier'              => self::DEFAULT_RETRY_MULTIPLIER,
            'retry_on_status'                       => self::DEFAULT_RETRY_ON_STATUS,
            'retry_on_timeout'                      => self::DEFAULT_RETRY_ON_TIMEOUT,
            'expose_retry_header'                   => self::DEFAULT_RETRY_EXPOSE_RETRY_HEADER,
            'retry_log_error'                       => self::DEFAULT_RETRY_LOG_ERROR,
            'retry_loggable_callback'               => self::DEFAULT_RETRY_LOGGABLE_CALLBACK,
            'retry_log_callback'                    => self::DEFAULT_RETRY_LOG_CALLBACK,
            'retry_log_file'                        => self::DEFAULT_RETRY_LOG_FILE,
            'retry_log_format'                      => self::DEFAULT_RETRY_LOG_FORMAT
        ], $defaultOptions);

        // Add our custom retry callback for logging if logging is enabled
        if ($mergedOptions['retry_log_error']) {
            $mergedOptions['on_retry_callback'] = self::createRetryCallback($mergedOptions);
        }

        // Use the upstream factory with our merged options
        return BaseGuzzleRetryMiddleware::factory($mergedOptions);
    }

    /**
     * Create the callback function for 'on_retry_callback' option
     * @see https://packagist.org/packages/caseyamcl/guzzle_retry_middleware
     *
     * @param array $options
     * @return callable
     */
    protected static function createRetryCallback(array $options): callable {
        return function(
            int $attemptNumber,
            float $delay,
            RequestInterface $request,
            array $requestOptions,
            ?ResponseInterface $response = null
        ) use ($options): void {
            // Merge request-specific options with our stored options
            $effectiveOptions = array_replace($options, $requestOptions);

            if (
                $effectiveOptions['retry_log_error'] &&                      // log retry errors
                ($attemptNumber >= $effectiveOptions['max_retry_attempts'])  // retry limit reached
            ) {
                if (
                    (is_callable($isLoggable = $effectiveOptions['retry_loggable_callback']) ? $isLoggable($request) : true) &&
                    is_callable($log = $effectiveOptions['retry_log_callback'])
                ) {
                    $logData = [
                        'url'               => $request->getUri()->__toString(),
                        'retryAttempt'      => $attemptNumber,
                        'maxRetryAttempts'  => $effectiveOptions['max_retry_attempts'],
                        'delay'             => $delay
                    ];

                    $message = self::getLogMessage(
                        $effectiveOptions['retry_log_format'],
                        $request,
                        $attemptNumber,
                        $effectiveOptions['max_retry_attempts'],
                        $response
                    );

                    $log($effectiveOptions['retry_log_file'], 'critical', $message, $logData, 'warning');
                }
            }
        };
    }

    /**
     * Format log message with placeholders
     *
     * @param string $message
     * @param RequestInterface $request
     * @param int $attemptNumber
     * @param int $maxRetryAttempts
     * @param ResponseInterface|null $response
     * @return string
     */
    protected static function getLogMessage(
        string $message,
        RequestInterface $request,
        int $attemptNumber,
        int $maxRetryAttempts,
        ?ResponseInterface $response = null
    ): string {
        $replace = [
            '{attempt}'     => $attemptNumber,
            '{maxRetry}'    => $maxRetryAttempts,
            '{method}'      => $request->getMethod(),
            '{target}'      => $request->getRequestTarget(),
            '{version}'     => $request->getProtocolVersion(),
            '{code}'        => $response ? $response->getStatusCode() : 'NULL',
            '{phrase}'      => $response ? $response->getReasonPhrase() : ''
        ];

        return str_replace(array_keys($replace), array_values($replace), $message);
    }
}
