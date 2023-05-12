<?php

namespace diaeai\Curl;

class StreamWrapper
{
    /** @var resource */
    public $context;

    private false|\CurlHandle $curl_handle;

    private int $position = 0;
    private mixed $size;

    /** @var string r, r+, or w */
    private string $mode;

    /**
     * Registers the stream wrapper if needed
     */
    public static function register(): void
    {
        if (in_array('curl', stream_get_wrappers())) {
            stream_wrapper_unregister('curl');
        }

        stream_wrapper_register('curl', __CLASS__, STREAM_IS_URL);
    }

    public function stream_cast(int $cast_as): bool
    {
        return false;
    }

    public function stream_close(): void
    {
        curl_close($this->curl_handle);
    }

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        $this->mode = rtrim($mode, 'bt');

        // Initialize a new cURL session
        $this->curl_handle = curl_init('https://'.$this->getKey($path));

        // Set cURL options for reading from the stream
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl_handle, CURLOPT_NOBODY, true);

        // Execute the cURL session
        curl_exec($this->curl_handle);

        // Set the content length for the stream
        $this->size = curl_getinfo($this->curl_handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        // Set the position to the beginning of the stream
        $this->position = 0;

        // Set the options
        curl_setopt($this->curl_handle, CURLOPT_NOBODY, false);

        return true;
    }

    public function stream_read(int $count): string
    {
        // Set the range for the next chunk of data to read
        $range = $this->position . '-' . ($this->position + $count - 1);

        // Set the range header for the cURL
        curl_setopt($this->curl_handle, CURLOPT_RANGE, $range);

        // Execute the cURL session to read the next chunk of data
        $data = curl_exec($this->curl_handle);

        // Update the position
        $this->position += strlen($data);

        // Return the data
        return $data;
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    public function stream_eof(): bool
    {
        return ($this->position >= $this->size);
    }

    public function stream_seek($offset, $whence): bool
    {
        switch ($whence) {
            case SEEK_SET:
                // Set the position to the given offset
                $this->position = $offset;
                break;

            case SEEK_CUR:
                // Move the position by the given offset
                $this->position += $offset;
                break;

            case SEEK_END:
                // Set the position to the end of the stream plus the given offset
                $this->position = $this->size + $offset;
                break;

            default:
                return false;
        }

        // Check if the new position is within the stream bounds
        if ($this->position < 0 || $this->position > $this->size) {
            return false;
        }

        // Return success
        return true;
    }

    public function stream_stat(): array
    {
        static $modeMap = [
            'r'  => 33060,
            'rb' => 33060,
            'r+' => 33206,
            'w'  => 33188,
            'wb' => 33188
        ];

        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => $modeMap[$this->mode],
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => $this->size ?: 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0
        ];
    }

    public function url_stat(string $path, int $flags): array
    {
        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 0100644,
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => $this->getSize($path) ?: 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0
        ];
    }

    private function getSize(string $path): int
    {
        // Initialize a new cURL session
        $curl_handle = curl_init('https://'.$this->getKey($path));

        // Set cURL options for reading from the stream
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_HEADER, false);
        curl_setopt($curl_handle, CURLOPT_NOBODY, true);

        // Execute the cURL session
        curl_exec($curl_handle);

        // Set the content length for the stream
        $size = curl_getinfo($curl_handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        curl_close($curl_handle);

        return $size;
    }

    private function getKey(string $path): ?string
    {
        return substr($path, strpos($path, '://') + 3);
    }
}
