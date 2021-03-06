<?php
/**
 * LoadsysStandardTest
 *
 * Test the phpcs code sniff "standard" using a collection of sample
 * files that have been annotated to signal whether they should pass
 * all sniffs in the ruleset, or should fail specific tests. Whether
 * a given sample file is expected to pass sniffs or fail specific
 * sniffs is determined using a specially formatted comment string
 * on the first line of the file after the opening `<?php`. Example:
 *
 *    <?php //~StandardName.SectionName.SniffName.RuleName,Second.Expected.Failed.RuleName
 *
 * The format is matched by this regular expression:
 *
 *    |//~(.*)$|
 *
 * And then passed through `explode(',')` to obtain the array. While
 * multiple failed sniffs are suppored, this is discouraged. The
 * preferred approach is to construct the minimal code example in each
 * file to fail **exactly one** sniff.
 *
 * This annotation MUST exist on the first line of the file.
 *
 * For backwards compatibility, if a sample file's name ends in
 * `_pass.php`, then any annotations inside the file are ignored.
 * Likewise, a file whose name does not end in `_pass.php` but does not
 * specify any failed tests in the manner described above will be
 * expected to fail sniffing in an unspecified way.
 */
class LoadsysStandardTest extends PHPUnit_Framework_TestCase {

	/**
	 * setUp
	 *
	 * Create a single instance of the wrapper around `phpcs`.
	 */
	public function setUp() {
		parent::setUp();

		if (!isset($this->helper)) {
			$this->helper = new TestHelper();
		}
	}

	/**
	 * tearDown
	 */
	public function tearDown() {
		//unset($this->helper);

		parent::tearDown();
	}

	/**
	 * Execute the codesniffer on the provided $file path using $standard.
	 *
	 * Accepts an array of fully qualified rule names that are expected to
	 * be triggered by the sniffing. An empty array indicates that the
	 * file should PASS all sniffs.
	 *
	 * @param string $file Full file path to the sample file to sniff.
	 * @param string $standard The short name of the coding standard to use during sniffing.
	 * @param array $expectedFailures Array of Sniff names expected in the output. Empty array indicates file should pass all sniffs.
	 * @dataProvider provideSampleFiles
	 */
	public function testSampleFile($file, $standard, $expectedFailures) {
		$outputStr = $this->helper->runPhpCs($file);

		$coveredSniffs = [];
		if (empty($expectedFailures)) {
			$this->assertNotRegExp(
				"/FOUND \d+ ERROR/",
				$outputStr,
				basename($file) . ' - expected to pass with no errors, some were reported.'
			);
		} else {
			foreach ($expectedFailures as $sniffName) {
				$sniff = '|\(' . $sniffName . '\)|';

				$this->assertRegExp(
					$sniff,
					$outputStr,
					basename($file) . " - expected to fail sniff `{$sniffName}`, but was not reported."
				);
			}
		}

		return $coveredSniffs;
	}

	/**
	 * provideSampleFiles
	 *
	 * Run simple syntax checks, if the filename ends with pass.php - expect it to pass
	 * Parse the first line of the file, looking for a comment annotating the sniffs that are expected to fail.
	 *
	 * @return array Sets of `[sample file path, stadard name, sniff names expected to fail]`.
	 */
	public function provideSampleFiles() {
		$standard = 'Loadsys';

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(dirname(__FILE__) . '/files')
		);
		$tests = [];
		foreach ($iterator as $dir) {
			// Skip directory entries and .dot files.
			if ($dir->isDir() || strpos($dir->getBasename(), '.') === 0) {
				continue;
			}

			$file = $dir->getPathname();
			$expectedFailures = $this->expectedFailures($file);

			// This is a meta-check on the sample files' filenames
			// and their expected sniff failure annotations.
			if (substr($file, -8) === 'pass.php') { // File should pass.
				$this->assertTrue(
					empty($expectedFailures),
					basename($file) . ' - declares its filename as a `_pass`ing test, but defines expected sniff failures: ' . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $expectedFailures)
				);
				$expectedFailures = []; // Artifically treat the file like it should pass.
			} else { // File should fail.
				$this->assertTrue(
					count($expectedFailures) > 0,
					basename($file) . ' - is named as a "failing" test, but does not define expected sniff failures. See README -> Testing'
				);
			}

			$tests[] = [$file, $standard, $expectedFailures];
		}
		return $tests;
	}

	/**
	 * expectedFailures
	 *
	 * Scans the first line of the provided $file for an "expected sniff
	 * failure" annotation. If found, returns the list of sniff names
	 * the sample file expects to fail as an array. Returns an empty
	 * array to indicate no failures are expected (the file should pass
	 * all sniffs).
	 *
	 * @param string $file
	 * @return array
	 */
	protected function expectedFailures($file) {
		$firstLine = fgets(fopen($file, 'r'));
		preg_match('|//~\s*(.*)$|', $firstLine, $matches);
		$sniffs = (isset($matches[1]) ? explode(',', $matches[1]) : []);
		return array_filter(array_map('trim', $sniffs));
	}


	/**
	 * Meta-test to check coverage of the sniffs in the Standard.
	 *
	 * Compares the list of sniffs from the coding standard to the
	 * accumulated list of sniffs detected in all sample files.
	 *
	 * Will emit an assertion failure for every sniff from the Standard
	 * that remains un-tested by the sum of the sample files.
	 *
	 * CLI equivalent:
	 *   `vendor/bin/phpcs -e --standard=./Loadsys/ Loadsys | grep '^ ' | sed 's/^ //'`
	 *
	 */
	public function testUntested() {
		$this->markTestIncomplete('TODO: Add sample files to cover everything listed in the output from this test. Comment this line out to work on adding the missing tests. Remove this line when this test passes.');

		$allSniffsInStandard = $this->helper->sniffList();

		$this->assertNotEmpty(
			$allSniffsInStandard,
			'List of sniffs active in this coding standard must not be empty.'
		);

		$coveredSniffs = $this->gatherCoveredSniffs();
		$sniffsNotCovered = array_diff($allSniffsInStandard, $coveredSniffs);
		$sep = PHP_EOL . '  - ';

		$this->assertEmpty(
			$sniffsNotCovered,
			'All sniffs from the coding standard (' . count($allSniffsInStandard) . ') are expected to be covered by annotated sample files representing sniff failure cases. The following sniffs are not represented by sample files expected to fail (' . count($sniffsNotCovered) . '):' . $sep . implode($sep, $sniffsNotCovered)
		);
	}

	/**
	 * Internal helper to consolidate a flat list of covered sniff names.
	 *
	 * Uses the output from provideSampleFiles() to generate a list of
	 * sniff names that the sample files intend to "cover".
	 *
	 * @return array A flat, indexed, sorted list of Sniff annotations detected in all sample files.
	 */
	protected function gatherCoveredSniffs() {
		$sampleFiles = $this->provideSampleFiles();

		$coveredSniffs = array_filter(array_column($sampleFiles, 2));
		$coveredSniffs = iterator_to_array(
			new RecursiveIteratorIterator(
				new RecursiveArrayIterator($coveredSniffs)
			),
			false
		);
		$coveredSniffs = array_map(function ($v) {
			return implode('.', array_slice(explode('.', $v), 0, 3));
		}, $coveredSniffs);
		$coveredSniffs = array_keys(array_flip($coveredSniffs)); // Remove dupes.
		sort($coveredSniffs);

		return $coveredSniffs;
	}
}
