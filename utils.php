<?php declare(strict_types=1);

const CONFIG_FILE = __DIR__ .'/config.php';

// PHP <8.0 support
if ( ! function_exists('str_contains')) {
	function str_contains($haystack, $needle)
	{
		return $needle !== '' && mb_strpos($haystack, $needle) !== false;
	}
}

function upsert_config_constants(array $keyvalues, string $filename = CONFIG_FILE)
{
	$config = file_get_contents($filename);
	if ( ! $config) {
		throw new \RuntimeException('Unable to read '. $filename);
	}

	foreach ($keyvalues as $name => $value) {
		if (str_contains($config, $name)) {
			$config = preg_replace('/([\n^]const '. $name .'\s*=\s*[\'"])[0-9a-z\s]*([\'"])/', "\\1$value\\2", $config);
		} else {
			$config .= "\nconst $name = '$value';\n";
		}
	}

	if (false === file_put_contents($filename, $config)) {
		throw new \RuntimeException('Unable to write to '. $filename);
	}
}
