<?php

/** A gRPC message stream. HTTP/2 frame boundaries are deliberately hidden. */
final class GRpcStream {
    private string $buffer = '';
    private bool $eof = false;

    /**
     * @param Closure $read Returns the next bytes, '' at EOF, or false on transport failure.
     * @param Closure $write Writes encoded bytes and returns success.
     * @param string|null $encoding Incoming per-message compression.
     */
    public function __construct(
        private Closure $read,
        private Closure $write,
        private ?string $encoding = null
    ) {}

    /** @param string|null $encoding Incoming per-message compression selected by response headers. */
    public function setEncoding(?string $encoding): void {
        $this->encoding = $encoding;
    }

    /**
     * @param string $class Protobuf message class to decode.
     * @return object|null The next message, or null after a clean half-close.
     */
    public function recv(string $class): ?object {
        if (!$this->fill(5)) {
            if ($this->buffer !== '') {
                throw new UnexpectedValueException('Incomplete gRPC frame header');
            }
            return null;
        }
        $header = unpack('Cflag/Nlength', $this->buffer);
        if ($header['flag'] > 1) {
            throw new UnexpectedValueException('Invalid gRPC compression flag');
        }
        if ($header['length'] > GRpcServer::MAX_MESSAGE_LENGTH) {
            throw new LengthException('gRPC message exceeds size limit');
        }
        $length = 5 + $header['length'];
        if (!$this->fill($length)) {
            throw new UnexpectedValueException('Incomplete gRPC message');
        }
        $frame = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);
        return GRpcServer::decode($class, $frame, null, $this->encoding);
    }

    /**
     * @param object $message Protobuf message to send.
     */
    public function send(object $message): void {
        $frame = GRpcServer::encode($message);
        if (strlen($frame) - 5 > GRpcServer::MAX_MESSAGE_LENGTH) {
            throw new LengthException('gRPC message exceeds size limit');
        }
        if (!(($this->write)($frame))) {
            throw new RuntimeException('gRPC write failed');
        }
    }

    /** @param int $length Minimum number of buffered bytes required. */
    private function fill(int $length): bool {
        while (strlen($this->buffer) < $length && !$this->eof) {
            $chunk = ($this->read)();
            if ($chunk === false) {
                throw new RuntimeException('gRPC read failed');
            }
            $this->eof = $chunk === '';
            $this->buffer .= $chunk;
        }
        return strlen($this->buffer) >= $length;
    }
}
