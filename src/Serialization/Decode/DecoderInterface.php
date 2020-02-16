<?php

declare(strict_types=1);

namespace Mitra\Serialization\Decode;

interface DecoderInterface
{
    /**
     * @param string $data
     * @param string $mimeType
     * @return array
     * @throw UnsupportedMimeTypeException
     */
    public function decode(string $data, string $mimeType): array;
}
