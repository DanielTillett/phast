<?php

new class {

    public function __construct() {
        echo $this->mergeFiles($this->recursiveGlob(
            '[A-Z]*.php',
            __DIR__ . '/src',
            __DIR__ . '/vendor/mrclay/jsmin-php/src/JSMin'
        ));
    }

    private function mergeFiles(Traversable $files) {
        $contents = [];
        foreach ($files as $file) {
            $contents[] = $this->readScript($file);
        }
        $contents = $this->sortScripts($contents);
        $contents = $this->sortScripts($contents);
        return "<?php\n" . implode("\n", $contents);
    }

    private function readScript($file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Could not read file: $file");
        }
        if (!preg_match('~^<\?php\s+(.*)$~s', $contents, $match)) {
            throw new RuntimeException("File does not look like PHP: $file");
        }
        $contents = $match[1];
        $contents = $this->scopeNamespace($contents);
        return $contents;
    }

    private function scopeNamespace($code) {
        return preg_replace('~
            ^ \s*
            namespace \s+ ([^;]+); \s*
            (.*) $
        ~xsi', "namespace $1 {\n$2\n}\n\n", $code);
    }

    private function sortScripts($scripts) {
        $result = [];
        foreach ($scripts as $scriptIndex => $script) {
            if (!isset($scripts[$scriptIndex])) {
                continue;
            }
            $scripts[$scriptIndex] = null;
            $uses = $this->getUses($script);
            foreach ($scripts as $dependencyIndex => $dependency) {
                if (!isset($scripts[$dependencyIndex])) {
                    continue;
                }
                $declares = $this->getDeclares($dependency);
                if (array_intersect($uses, $declares)) {
                    $scripts[$dependencyIndex] = null;
                    $result[] = $dependency;
                }
            }
            $result[] = $script;
        }
        return $result;
    }

    private function getUses($script) {
        $pattern = '~
            (?:^|;) \s*
            (?:abstract\s+)? class \s+
            \w+ \s+
            (?:implements|extends) \s+ (\w+)
        ~xsi';
        return $this->getNamesWithPattern($pattern, $script);
    }

    private function getDeclares($script) {
        $pattern = '~
            (?:^|;) \s*
            (?: (?:abstract\s+)? class|interface|trait ) \s+
            (\w+)
        ~xsi';
        return $this->getNamesWithPattern($pattern, $script);
    }

    private function getNamesWithPattern($pattern, $script) {
        $names = [];
        preg_replace_callback($pattern, function ($match) use (&$names) {
            $names[] = $this->normalizeName($match[1]);
        }, $script);
        return $names;
    }

    private function normalizeName($name) {
        return strtolower($name);
    }

    private function recursiveGlob($pattern, ...$directories) {
        foreach ($directories as $directory) {
            foreach ($this->recursiveList($directory) as $path) {
                if (fnmatch($pattern, basename($path))) {
                    yield $path;
                }
            }
        }
    }

    private function recursiveList($directory) {
        $list = scandir($directory);
        if (empty($list)) {
            throw new RuntimeException("Could not read directory: $directory");
        }
        foreach ($list as $entry) {
            if ($entry[0] === '.') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                yield from $this->recursiveList($path);
            } else {
                yield $path;
            }
        }
    }

};
