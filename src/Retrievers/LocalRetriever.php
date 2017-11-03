<?php

namespace Kibo\Phast\Retrievers;

use Kibo\Phast\Common\ObjectifiedFunctions;
use Kibo\Phast\ValueObjects\URL;

class LocalRetriever implements Retriever {

    /**
     * @var array
     */
    private $map;

    /**
     * @var ObjectifiedFunctions
     */
    private $funcs;

    /**
     * LocalRetriever constructor.
     *
     * @param array $map
     * @param ObjectifiedFunctions|null $functions
     */
    public function __construct(array $map, ObjectifiedFunctions $functions = null) {
        $this->map = $map;
        if ($functions) {
            $this->funcs = $functions;
        } else {
            $this->funcs = new ObjectifiedFunctions();
        }
    }

    public function retrieve(URL $url) {
        $file = $this->getFileForURL($url);
        if ($file === false) {
            return false;
        }
        return @$this->funcs->file_get_contents($file);
    }

    public function getLastModificationTime(URL $url) {
        $file = $this->getFileForURL($url);
        if ($file === false) {
            return false;
        }
        return @$this->funcs->filemtime($file);
    }

    private function getFileForURL(URL $url) {
        if (!isset ($this->map[$url->getHost()])) {
            return false;
        }
        $submap = $this->map[$url->getHost()];
        if (!is_array($submap)) {
            return $this->appendNormalized($submap, $url->getPath());
        }

        $prefixes = array_keys($submap);
        usort($prefixes, function ($prefix1, $prefix2) {
            return strlen($prefix1) > strlen($prefix2) ? -1 : 1;
        });

        foreach ($prefixes as $prefix) {
            $pattern = '|^' . preg_quote($prefix, '|') . '(?<path>.*)|';
            if (preg_match($pattern, $url->getPath(), $matches)) {
                return $this->appendNormalized($submap[$prefix], $matches['path']);
            }
        }
        return false;
    }

    private function appendNormalized($target, $appended) {
        $appended = explode("\0", $appended)[0];
        $appended = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $appended);

        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $appended), function ($part) {
            return !empty ($part) && $part != '.';
        });
        $absolutes = array();
        foreach ($parts as $part) {
            if ($part == '..' && empty ($absolutes)) {
                return false;
            }
            if ($part == '..') {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        array_unshift($absolutes, $target);
        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }

}
