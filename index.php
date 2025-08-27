<?php
declare(strict_types=1);

ini_set('display_errors', '0');  // don’t leak stack traces
ini_set('log_errors', '1');
@set_time_limit(5);                    // short runtime
@ini_set('memory_limit', '64M'); // small memory cap is fine for this

header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

/// @brief   City Name Generator (PHP port, web-only)
/// @details Loads citynamegen_data.json from the same folder and renders a form to generate city names or show stats.
/// @author  Frank Willeke

// --------------------------------------------------------------------------------------
// Config / Metadata
// --------------------------------------------------------------------------------------

/** @var string */
const SCRIPTTITLE = 'City Name Generator';
/** @var string */
const SCRIPTVERSION = '1.0.3';
/** @var string */
const DATAFILENAME = 'citynamegen_data.json';

// --------------------------------------------------------------------------------------
// Utilities
// --------------------------------------------------------------------------------------

/**
 * @brief Random float in [0,1).
 * @return float
 */
function frand(): float
{
	return mt_rand() / (mt_getrandmax() + 1);
}

/**
 * @brief Safe array_get with default.
 * @param[in] arr
 * @param[in] key
 * @param[in] default
 * @return mixed
 */
function array_get(array $arr, string $key, mixed $default = null): mixed
{
	return array_key_exists($key, $arr) ? $arr[$key] : $default;
}

/**
 * @brief Title-case a UTF-8 string.
 * @param[in] s
 * @return string
 */
function titlecase(string $s): string
{
	return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
}

// --------------------------------------------------------------------------------------
// City Name Generator
// --------------------------------------------------------------------------------------

/**
 * @brief City name generator core class.
 */
final class CityNameGenerator
{
	// Thresholds (probabilities to ADD the feature if frand() > threshold)
	private float $_prefixThreshold = 0.85; ///< Chance trigger for adding a prefix
	private float $_suffixThreshold = 0.89; ///< Chance trigger for adding a suffix
	private float $_doubleThreshold = 0.90; ///< Chance trigger for hyphenated double base

	/** @var string[] */
	private array $_prefixes = [];
	/** @var string[] */
	private array $_suffixes = [];
	/** @var array{0:string[],1:string[]} */
	private array $_parts = [[], []];

	/**
	 * @brief Load JSON data file.
	 * @param[in] filePath Absolute or relative path.
	 * @return bool True on success.
	 */
	public function loadData(string $filePath): bool
	{
		if (!is_file($filePath))
		{
			return false;
		}
		$json = file_get_contents($filePath);
		if ($json === false)
		{
			return false;
		}
		$data = json_decode($json, true);
		if (!is_array($data))
		{
			return false;
		}

		// Settings (optional overrides)
		$settings = (array)array_get($data, 'settings', []);
		$this->_prefixThreshold = (float)array_get($settings, 'prefixThreshold', $this->_prefixThreshold);
		$this->_suffixThreshold = (float)array_get($settings, 'suffixThreshold', $this->_suffixThreshold);
		$this->_doubleThreshold = (float)array_get($settings, 'doubleThreshold', $this->_doubleThreshold);

		// Strings
		$strings = (array)array_get($data, 'strings', []);

		// Lists
		$this->_prefixes = (array)array_get($data, 'prefixes', []);
		$this->_suffixes = (array)array_get($data, 'suffixes', []);

		$parts = (array)array_get($data, 'parts', []);
		if (!isset($parts[0]) || !isset($parts[1]) || !is_array($parts[0]) || !is_array($parts[1]))
		{
			return false;
		}
		$this->_parts = [$parts[0], $parts[1]];

		// Minimal validation
		return (count($this->_parts[0]) > 0) && (count($this->_parts[1]) > 0);
	}

	/**
	 * @brief Compute stats (counts and combinatorics; probabilities are not applied here).
	 * @return array<string,mixed>
	 */
	public function computeStats(): array
	{
		$p0 = count($this->_parts[0]);
		$p1 = count($this->_parts[1]);
		$pref = count($this->_prefixes);
		$suff = count($this->_suffixes);

		$base = $p0 * $p1;               // single base: part0 + part1
		$double = $base * $base;         // hyphenated base-base (ordered pairs)
		$withPrefixes = $base * ($pref + 1);
		$withSuffixes = $base * ($suff + 1);
		$withBoth = $base * ($pref + 1) * ($suff + 1);

		// A rough "total" space including doubles and affixes (not de-duplicated)
		$total_with_double = ($base + $double) * ($pref + 1) * ($suff + 1);

		return [
			'parts' => ['first' => $p0, 'second' => $p1, 'base' => $base],
			'prefixes' => $pref,
			'suffixes' => $suff,
			'variants' => [
				'base' => $base,
				'with_prefixes' => $withPrefixes,
				'with_suffixes' => $withSuffixes,
				'with_prefixes_and_suffixes' => $withBoth,
				'double_base' => $double,
				'approx_total_incl_double' => $total_with_double
			]
		];
	}

	/**
	 * @brief Print stats in a human-friendly way.
	 * @param[in] stats
	 * @return void
	 */
	public function printStatistics(array $stats): void
	{
		echo "Parts:\n";
		echo "------\n";
		printf("First parts           : %8s\n", number_format($stats['parts']['first']));
		printf("Second parts          : %8s\n", number_format($stats['parts']['second']));
		printf("Base names (P0×P1)    : %8s\n", number_format($stats['parts']['base']));
		echo "\n";
		echo "Affixes:\n";
		echo "--------\n";
		printf("Prefixes              : %8s\n", number_format($stats['prefixes']));
		printf("Suffixes              : %8s\n", number_format($stats['suffixes']));
		echo "\n";
		echo "Combinations (no probabilities applied):\n";
		echo "----------------------------------------\n";
		printf("Base only             : %15s\n", number_format($stats['variants']['base']));
		printf("With prefixes         : %15s\n", number_format($stats['variants']['with_prefixes']));
		printf("With suffixes         : %15s\n", number_format($stats['variants']['with_suffixes']));
		printf("With both             : %15s\n", number_format($stats['variants']['with_prefixes_and_suffixes']));
		printf("Hyphenated double     : %15s\n", number_format($stats['variants']['double_base']));
		printf("Approx total incl dbl : %15s\n", number_format($stats['variants']['approx_total_incl_double']));
	}

	/**
	 * @brief Generate a single base name from parts.
	 * @return string
	 */
	private function generateBase(): string
	{
		$a = $this->_parts[0][array_rand($this->_parts[0])];
		$b = $this->_parts[1][array_rand($this->_parts[1])];
		return titlecase($a . $b);
	}

	/**
	 * @brief Maybe add a prefix (word before the base).
	 * @param[in] base
	 * @return string
	 */
	private function maybeAddPrefix(string $base): string
	{
		if (empty($this->_prefixes))
		{
			return $base;
		}
		// Trigger if random is greater than threshold (e.g., 0.15 chance when threshold=0.85)
		if (frand() > $this->_prefixThreshold)
		{
			$pref = $this->_prefixes[array_rand($this->_prefixes)];
			return $pref . ' ' . $base;
		}
		return $base;
	}

	/**
	 * @brief Maybe add a suffix (phrase after the base).
	 * @param[in] base
	 * @return string
	 */
	private function maybeAddSuffix(string $base): string
	{
		if (empty($this->_suffixes))
		{
			return $base;
		}
		if (frand() > $this->_suffixThreshold)
		{
			$suf = $this->_suffixes[array_rand($this->_suffixes)];
			return $base . ' ' . $suf;
		}
		return $base;
	}

	/**
	 * @brief Generate a city name, possibly hyphenated double with optional prefix/suffix.
	 * @return string
	 */
	public function generate(): string
	{
		$base = $this->generateBase();

		// Optional double: CityA-CityB
		if (frand() > $this->_doubleThreshold)
		{
			$base2 = $this->generateBase();
			$base = $base . '-' . $base2;
		}

		$base = $this->maybeAddPrefix($base);
		$base = $this->maybeAddSuffix($base);
		return $base;
	}
} // class CityNameGenerator

/**
 * @brief	Read an int GET param within a range, fall back to default.
 * @param[in] key
 * @param[in] def
 * @param[in] min
 * @param[in] max
 * @return	int
 */
function getInt(string $key, int $def, int $min, int $max): int
{
	if (!isset($_GET[$key]))
	{
		return $def;
	}
	$v = (int)$_GET[$key];
	$v = max($min, min($max, $v));
	return $v;
}

// --------------------------------------------------------------------------------------
// Web Controller (only)
// --------------------------------------------------------------------------------------

$count_default = 10;
$count = getInt(key: 'count', def: $count_default, min: 1, max: 999);
$stats = isset($_GET['stats']);

$gen = new CityNameGenerator();
$dataFile = __DIR__ . DIRECTORY_SEPARATOR . DATAFILENAME;
$loaded = $gen->loadData($dataFile);

mt_srand((int)microtime(true));
?>
<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars(SCRIPTTITLE . ' ' . SCRIPTVERSION, ENT_QUOTES) ?></title>
	<style>
		body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 2rem; }
		fieldset { padding: 1rem; border-radius: 8px; }
		label { display: block; margin: 0.5rem 0 0.25rem; }
		input[type="number"] { width: 7rem; }
		pre { background: #111; color: #0f0; padding: 1rem; border-radius: 8px; overflow: auto; }
		.err { background: #fee; color: #900; padding: 0.75rem; border: 1px solid #f99; border-radius: 8px; }
		.footer { font-size: 0.75em; }
		.grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap: 1rem; }
		button { padding: .6rem 1rem; border-radius: 10px; border: 1px solid #ccc; color: #111; -webkit-text-fill-color: #111; background: #f6f6f6; -webkit-appearance: none; appearance: none; cursor: pointer; }
		button:hover { background: #eee; }
		h2 small { color: #666; font-weight: normal; }
	</style>
</head>
<body>
	<h1><?= htmlspecialchars(SCRIPTTITLE . ' ' . SCRIPTVERSION, ENT_QUOTES) ?></h1>

	<form method="get">
		<fieldset class="grid">
			<div>
				<label for="count">Anzahl</label>
				<input id="count" name="count" type="number" min="1" step="1" value="<?= (int)$count ?>">
			</div>
			<div>
				<label>
					<input type="checkbox" name="stats" value="1"<?= $stats ? ' checked' : '' ?>>
					Statistik anzeigen
				</label>
			</div>
		</fieldset>
		<p style="margin-top:1rem">
			<button type="submit">Generieren!</button>
		</p>
	</form>

	<?php if (!$loaded): ?>
		<p class="err">Konnte <code><?= htmlspecialchars(DATAFILENAME, ENT_QUOTES) ?></code> im aktuellen Ordner nicht laden.</p>
	<?php else: ?>
		<?php if ($stats): ?>
			<h2>Statistik</h2>
			<pre><?php ob_start(); $gen->printStatistics($gen->computeStats()); echo htmlspecialchars(ob_get_clean(), ENT_QUOTES); ?></pre>
		<?php else: ?>
			<h2><?php echo $count; ?> Städte die man mal besuchen sollte:</h2>
			<pre><?php
				$out = '';
				for ($i = 0; $i < $count; ++$i)
				{
					$prefix = ($count > 1) ? str_pad((string)($i + 1), 2, ' ', STR_PAD_LEFT) . '. ' : '';
					$out .= $prefix . $gen->generate() . "\n";
				}
				echo htmlspecialchars($out, ENT_QUOTES);
			?></pre>
		<?php endif; ?>
	<?php endif; ?>
	<?php
	$otherApp = __DIR__ . '/../namegen/index.php';

	if (file_exists($otherApp))
	{
		echo '<p>Probier auch mal den <a href="../namegen/">German Name Generator</a>!</p>';
	}
	?>
	<p class="footer">&copy; 2025 by <a href="https://www.frankwilleke.de">www.frankwilleke.de</a></p>
</body>
</html>
