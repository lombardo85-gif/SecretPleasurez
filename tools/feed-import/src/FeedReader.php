<?php
/**
 * Streams a supplier feed (CSV or XML) and yields normalized associative rows.
 *
 * Rows are yielded one at a time so a 200k-line wholesaler feed does not have
 * to fit in memory.
 */

declare(strict_types=1);

final class FeedReader
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config the "feed" block of the job config */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return Generator<int,array<string,string>> feed rows keyed by source column name
     */
    public function rows(): Generator
    {
        $format = strtolower((string) ($this->config['format'] ?? 'csv'));

        switch ($format) {
            case 'csv':
                yield from $this->readCsv();
                break;
            case 'xml':
                yield from $this->readXml();
                break;
            default:
                throw new RuntimeException(sprintf('Unsupported feed format "%s" (expected csv or xml).', $format));
        }
    }

    /**
     * Resolve the feed to a local file. Remote feeds are downloaded first so a
     * mid-transfer disconnect fails before we start writing to the catalog
     * rather than halfway through it.
     */
    private function localPath(): string
    {
        $source = (string) ($this->config['source'] ?? '');
        if ($source === '') {
            throw new RuntimeException('feed.source is required.');
        }

        // Local file: use as-is.
        if (!preg_match('#^[a-z0-9+.-]+://#i', $source)) {
            if (!is_readable($source)) {
                throw new RuntimeException(sprintf('Feed file not readable: %s', $source));
            }

            return $source;
        }

        $cacheDir = dirname(__DIR__) . '/var';
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
            throw new RuntimeException(sprintf('Cannot create cache directory: %s', $cacheDir));
        }

        $target = $cacheDir . '/feed-' . date('Ymd-His') . '.' . strtolower((string) ($this->config['format'] ?? 'csv'));

        $context = stream_context_create([
            'http' => [
                'timeout' => (int) ($this->config['timeout'] ?? 120),
                'header' => $this->httpHeaders(),
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
            'ftp' => ['overwrite' => true],
        ]);

        $in = @fopen($source, 'rb', false, $context);
        if ($in === false) {
            throw new RuntimeException(sprintf('Could not open feed URL: %s', $source));
        }

        $out = fopen($target, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException(sprintf('Could not write feed cache: %s', $target));
        }

        $bytes = stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        if ($bytes === false || $bytes === 0) {
            @unlink($target);
            throw new RuntimeException('Feed download returned no data — refusing to treat an empty feed as a catalog wipe.');
        }

        return $target;
    }

    /** @return string[] */
    private function httpHeaders(): array
    {
        $headers = [];
        $auth = $this->config['auth'] ?? null;
        if (is_array($auth) && isset($auth['user'], $auth['password'])) {
            $headers[] = 'Authorization: Basic ' . base64_encode($auth['user'] . ':' . $auth['password']);
        }
        foreach ((array) ($this->config['headers'] ?? []) as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        return $headers;
    }

    /**
     * @return Generator<int,array<string,string>>
     */
    private function readCsv(): Generator
    {
        $path = $this->localPath();
        $delimiter = (string) ($this->config['delimiter'] ?? ',');
        $enclosure = (string) ($this->config['enclosure'] ?? '"');
        $encoding = strtoupper((string) ($this->config['encoding'] ?? 'UTF-8'));

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not open feed file: %s', $path));
        }

        try {
            $header = null;
            $lineNo = 0;

            while (($values = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false) {
                ++$lineNo;

                // fgetcsv yields [null] for a blank line.
                if ($values === [null] || $values === []) {
                    continue;
                }

                if ($encoding !== 'UTF-8') {
                    $values = array_map(
                        static fn ($v) => $v === null ? null : mb_convert_encoding((string) $v, 'UTF-8', $encoding),
                        $values
                    );
                }

                if ($header === null) {
                    // Strip a UTF-8 BOM from the first header cell, or the first
                    // column name never matches the mapping.
                    if (isset($values[0])) {
                        $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $values[0]);
                    }
                    $header = array_map(static fn ($v) => trim((string) $v), $values);
                    continue;
                }

                // Tolerate ragged rows rather than aborting the whole run.
                $values = array_slice(array_pad($values, count($header), ''), 0, count($header));

                $row = array_combine($header, array_map(static fn ($v) => trim((string) $v), $values));
                $row['__line'] = (string) $lineNo;

                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return Generator<int,array<string,string>>
     */
    private function readXml(): Generator
    {
        $path = $this->localPath();
        $itemNode = (string) ($this->config['item_node'] ?? 'product');

        $reader = new XMLReader();
        if (!$reader->open('file://' . realpath($path))) {
            throw new RuntimeException(sprintf('Could not open XML feed: %s', $path));
        }

        $index = 0;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== $itemNode) {
                    continue;
                }

                $xml = $reader->readOuterXml();
                if ($xml === '') {
                    continue;
                }

                $element = @simplexml_load_string($xml);
                if ($element === false) {
                    continue;
                }

                ++$index;
                $row = $this->flatten($element);
                $row['__line'] = (string) $index;

                yield $row;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Flatten one XML item into dot-notation keys, so mappings can address
     * nested nodes as "stock.quantity" and attributes as "item.@sku".
     *
     * @return array<string,string>
     */
    private function flatten(SimpleXMLElement $element, string $prefix = ''): array
    {
        $out = [];

        foreach ($element->attributes() as $name => $value) {
            $out[($prefix === '' ? '' : $prefix . '.') . '@' . $name] = trim((string) $value);
        }

        foreach ($element->children() as $name => $child) {
            $key = $prefix === '' ? (string) $name : $prefix . '.' . $name;

            if ($child->count() > 0 || $child->attributes()->count() > 0) {
                $out += $this->flatten($child, $key);
            }

            if ($child->count() === 0) {
                // A repeated tag keeps its first value under the plain key and
                // every value under "key.N", so feeds that repeat <category>
                // are still addressable.
                if (!array_key_exists($key, $out)) {
                    $out[$key] = trim((string) $child);
                }
                $n = 0;
                while (array_key_exists($key . '.' . $n, $out)) {
                    ++$n;
                }
                $out[$key . '.' . $n] = trim((string) $child);
            }
        }

        if ($prefix !== '' && $element->count() === 0) {
            $out[$prefix] = trim((string) $element);
        }

        return $out;
    }
}
