<?php declare(strict_types=1);

const CONFIG_FILE = __DIR__ .'/config.php';

function upsert_config_constants($keyvalues)
{
	$config = file_get_contents(CONFIG_FILE);
	if ( ! $config) {
		throw new \RuntimeException('Unable to read '. CONFIG_FILE);
	}

	foreach ($keyvalues as $name => $value) {
		if (str_contains($config, $name)) {
			$config = preg_replace('/([\n^]const '. $name .'\s.*?[\'"])[0-9a-z]+([\'"])/', "\\1$value\\2", $config);
		} else {
			$config .= "\nconst $name = '$value';\n";
		}
	}

	if (false === file_put_contents(CONFIG_FILE, $config)) {
		throw new \RuntimeException('Unable to write to '. CONFIG_FILE);
	}
}
