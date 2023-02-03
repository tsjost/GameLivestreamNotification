<?php declare(strict_types=1);

require __DIR__ .'/../vendor/autoload.php';
require __DIR__ .'/../utils.php';

use \PHPUnit\Framework\TestCase;

const TESTCONFIG_FILE = __DIR__ . '/../testconfig.php';

final class UtilsTest extends TestCase
{
	public function test_upsert_config_constants(): void
	{
		upsert_config_constants([
			'HELLO'       => 'this is a greeting',
			'HELLO_THERE' => 'say hello you must',
			'FOO_BAR_BAZ' => 'lorem ipsum dolor sit amet',
			'NUMBERS'     => "123 can't allow numbers to break things",
		], TESTCONFIG_FILE);

		$expected = <<<EOF
<?php

const HELLO			= 'this is a greeting';
const HELLO_THERE='say hello you must';

const NUMBERS   =  '123 can't allow numbers to break things';

const FOO_BAR_BAZ      =     "lorem ipsum dolor sit amet";
EOF;
		$this->assertSame($expected, file_get_contents(TESTCONFIG_FILE));
	}

	protected function setUp(): void
	{
		file_put_contents(TESTCONFIG_FILE, <<<EOF
<?php

const HELLO			= '';
const HELLO_THERE='general kenobi';

const NUMBERS   =  'hello';

const FOO_BAR_BAZ      =     "stuff goes in here";
EOF);
	}

	public static function tearDownAfterClass(): void
	{
		unlink(TESTCONFIG_FILE);
	}
}
