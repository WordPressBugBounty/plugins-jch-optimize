<?php

namespace _JchOptimizeVendor\Laminas\Log\Writer;

use _JchOptimizeVendor\Laminas\Log\Exception;
use _JchOptimizeVendor\Laminas\Log\Formatter\Simple as SimpleFormatter;
use _JchOptimizeVendor\Laminas\Stdlib\ErrorHandler;
use Traversable;
class Stream extends AbstractWriter
{
    /**
     * Separator between log entries
     *
     * @var string
     */
    protected $logSeparator = \PHP_EOL;
    /**
     * Holds the PHP stream to log to.
     *
     * @var null|stream
     */
    protected $stream = null;
    /**
     * Constructor
     *
     * @param  string|resource|array|Traversable $streamOrUrl Stream or URL to open as a stream
     * @param  string|null $mode Mode, only applicable if a URL is given
     * @param  null|string $logSeparator Log separator string
     * @param  null|int $filePermissions Permissions value, only applicable if a filename is given;
     *     when $streamOrUrl is an array of options, use the 'chmod' key to specify this.
     * @return Stream
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    public function __construct($streamOrUrl, $mode = null, $logSeparator = null, $filePermissions = null)
    {
        if ($streamOrUrl instanceof Traversable) {
            $streamOrUrl = \iterator_to_array($streamOrUrl);
        }
        if (\is_array($streamOrUrl)) {
            parent::__construct($streamOrUrl);
            $mode = isset($streamOrUrl['mode']) ? $streamOrUrl['mode'] : null;
            $logSeparator = isset($streamOrUrl['log_separator']) ? $streamOrUrl['log_separator'] : null;
            $filePermissions = isset($streamOrUrl['chmod']) ? $streamOrUrl['chmod'] : $filePermissions;
            $streamOrUrl = isset($streamOrUrl['stream']) ? $streamOrUrl['stream'] : null;
        }
        // Setting the default mode
        if (null === $mode) {
            $mode = 'a';
        }
        if (!\is_string($streamOrUrl) && !\is_resource($streamOrUrl)) {
            throw new Exception\InvalidArgumentException(\sprintf('Resource is not a stream nor a string; received "%s', \gettype($streamOrUrl)));
        }
        if (\is_resource($streamOrUrl)) {
            if ('stream' != \get_resource_type($streamOrUrl)) {
                throw new Exception\InvalidArgumentException(\sprintf('Resource is not a stream; received "%s', \get_resource_type($streamOrUrl)));
            }
            if ('a' != $mode) {
                throw new Exception\InvalidArgumentException(\sprintf('Mode must be "a" on existing streams; received "%s"', $mode));
            }
            $this->stream = $streamOrUrl;
        } else {
            ErrorHandler::start();
            if (isset($filePermissions) && !\file_exists($streamOrUrl) && \is_writable(\dirname($streamOrUrl))) {
                \touch($streamOrUrl);
                \chmod($streamOrUrl, $filePermissions);
            }
            $this->stream = \fopen($streamOrUrl, $mode, \false);
            $error = ErrorHandler::stop();
        }
        if (!$this->stream) {
            throw new Exception\RuntimeException(\sprintf('"%s" cannot be opened with mode "%s"', $streamOrUrl, $mode), 0, $error);
        }
        if (null !== $logSeparator) {
            $this->setLogSeparator($logSeparator);
        }
        if ($this->formatter === null) {
            $this->formatter = new SimpleFormatter();
        }
    }
    /**
     * Write a message to the log.
     *
     * @param array $event event data
     * @return void
     * @throws Exception\RuntimeException
     */
    protected function doWrite(array $event)
    {
        $line = $this->formatter->format($event) . $this->logSeparator;
        \fwrite($this->stream, $line);
    }
    /**
     * Set log separator string
     *
     * @param  string $logSeparator
     * @return Stream
     */
    public function setLogSeparator($logSeparator)
    {
        $this->logSeparator = (string) $logSeparator;
        return $this;
    }
    /**
     * Get log separator string
     *
     * @return string
     */
    public function getLogSeparator()
    {
        return $this->logSeparator;
    }
    /**
     * Close the stream resource.
     *
     * @return void
     */
    public function shutdown()
    {
        if (\is_resource($this->stream)) {
            \fclose($this->stream);
        }
    }
}
