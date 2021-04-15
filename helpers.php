<?php

if (!function_exists('discover_foxsocial_packages_patterns')) {
    function discover_foxsocial_packages_patterns(): array
    {
        return [
            'modules/*/composer.json',
            'modules/*/*/composer.json',
            'modules/*/*/*/composer.json',
        ];
    }
}

if (!function_exists('discover_foxsocial_packages')) {
    function discover_foxsocial_packages(
        string $basePath,
        ?array $patterns = null,
        bool $writeToConfig = false,
        ?string $configFilename = null
    ): array {
        $files = [];
        $packageArray = [];
        $patterns = $patterns ?? discover_foxsocial_packages_patterns();

        array_walk($patterns, function ($pattern) use (&$files, $basePath) {
            $dir = rtrim($basePath, DIRECTORY_SEPARATOR,).DIRECTORY_SEPARATOR.$pattern;
            foreach (glob($dir) as $file) {
                $files[] = $file;
            }
        });

        array_walk($files, function ($file) use (&$packageArray, $basePath) {
            try {
                $data = json_decode(file_get_contents($file), true);
                if (!isset($data['extra']) ||
                    !isset($data['extra']['foxsocial'])
                    || !is_array($data['extra']['foxsocial'])) {
                    return;
                }

                $extra = $data['extra']['foxsocial'];
                $namespace = $extra['namespace'] ?? $data['autoload']['psr-4'] ?? '';

                if (is_array($namespace)) {
                    $namespace = array_key_first($namespace);
                }

                $packageArray[] = [
                    'name'       => $data['name'],
                    'core'       => (bool) ($extra['core'] ?? false),
                    'priority'   => (int) ($extra['priority'] ?? 99),
                    'version'    => $data['version'],
                    'nameAlias'  => $extra['nameAlias'],
                    'nameStudly' => $extra['nameStudly'],
                    'namespace'  => trim($namespace, '\\'),
                    'path'       => trim(substr(dirname($file), strlen($basePath)), DIRECTORY_SEPARATOR),
                    'providers'  => $extra['providers'] ?? [],
                    'aliases'    => $extra['aliases'] ?? [],
                ];
            } catch (Exception $exception) {
                echo $exception->getMessage(), PHP_EOL;
            }
        });

        usort($packageArray, function ($a, $b) {
            if ($a['core'] && $b['core']) {
                return $a['priority'] - $b['priority'];
            } elseif ($a['core']) {
                return -1;
            } elseif ($b['core']) {
                return 1;
            } else {
                return $a['core'] - $b['core'];
            }
        });

        $packages = [];
        // export to keys value.
        array_walk($packageArray, function ($item) use (&$packages) {
            $packages[$item['name']] = $item;
        });

        if ($writeToConfig) {
            $filename = $basePath.DIRECTORY_SEPARATOR.($configFilename ?? "config/foxsocial.php");

            /** @noinspection PhpIncludeInspection */
            $data = file_exists($filename) ? require $filename : [];
            $data['packages'] = $packages;

            if (false === file_put_contents($filename, sprintf('<?php return %s;', var_export($data, true)))) {
                echo "Could not write to file $filename";
            }

        }
        return $packages;
    }
}