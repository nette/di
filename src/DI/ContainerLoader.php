<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use function bin2hex, class_exists, file_get_contents, file_put_contents, flock, fopen, function_exists, hash, is_file, preg_quote, preg_replace, random_bytes, rename, serialize, sprintf, strlen, substr, unlink, unserialize;


/**
 * DI container loader.
 */
class ContainerLoader
{
	public function __construct(
		private readonly string $tempDirectory,
		private readonly bool $autoRebuild = false,
	) {
	}


	/**
	 * Loads the container class, generating it if not already cached. Returns the class name.
	 *
	 * In auto-rebuild mode, when the cached container file has been regenerated since the
	 * loaded class was first defined (e.g. user edited a config in a long-running process),
	 * a uniquely-named copy of the regenerated class is eval'd into memory and its name is
	 * returned instead. This bypasses PHP's inability to redeclare a loaded class and lets
	 * long-running processes (workers, dev servers, MCP inspector) pick up config edits live.
	 *
	 * @param  callable(Compiler): ?string  $generator
	 * @return class-string<Container>
	 */
	public function load(callable $generator, mixed $key = null): string
	{
		$class = $this->getClassName($key);
		return $this->loadFile($class, \Closure::fromCallable($generator));
	}


	/**
	 * Returns the container class name derived from the given key.
	 */
	public function getClassName(mixed $key): string
	{
		return 'Container_' . substr(hash('xxh128', serialize($key)), 0, 10);
	}


	/**
	 * @param  (\Closure(Compiler): ?string)  $generator
	 * @return class-string<Container>
	 */
	private function loadFile(string $class, \Closure $generator): string
	{
		$file = "$this->tempDirectory/$class.php";
		$alreadyLoaded = class_exists($class, autoload: false);

		// Fast path: cache valid → reuse loaded class or include the cached file.
		if (!$this->isExpired($file)) {
			if ($alreadyLoaded) {
				return $class;
			} elseif ((@include $file) !== false) { // @ file may not exist
				return $class;
			}
			// include failed (file vanished between checks) — fall through to regenerate.
		}

		Nette\Utils\FileSystem::createDir($this->tempDirectory);

		$handle = @fopen("$file.lock", 'c+'); // @ is escalated to exception
		if (!$handle) {
			throw new Nette\IOException(sprintf("Unable to create file '%s.lock'. %s", $file, Nette\Utils\Helpers::getLastError()));
		} elseif (!@flock($handle, LOCK_EX)) { // @ is escalated to exception
			throw new Nette\IOException(sprintf("Unable to acquire exclusive lock on '%s.lock'. %s", $file, Nette\Utils\Helpers::getLastError()));
		}

		$codeRegenerated = false;
		if (!is_file($file) || $this->isExpired($file, $updatedMeta)) {
			if (isset($updatedMeta)) {
				$toWrite["$file.meta"] = $updatedMeta;
			} else {
				[$toWrite[$file], $toWrite["$file.meta"]] = $this->generate($class, $generator);
				$codeRegenerated = true;
			}

			foreach ($toWrite as $name => $content) {
				if (file_put_contents("$name.tmp", $content) !== strlen($content) || !rename("$name.tmp", $name)) {
					@unlink("$name.tmp"); // @ - file may not exist
					throw new Nette\IOException(sprintf("Unable to create file '%s'.", $name));
				} elseif (function_exists('opcache_invalidate')) {
					@opcache_invalidate($name, force: true); // @ can be restricted
				}
			}
		}

		flock($handle, LOCK_UN);

		// Class not in memory yet → standard include path.
		if (!$alreadyLoaded) {
			if ((@include $file) === false) { // @ - error escalated to exception
				throw new Nette\IOException(sprintf("Unable to include '%s'.", $file));
			}
			return $class;
		}

		// Class already loaded but the file was just regenerated — PHP cannot redeclare
		// a loaded class, so eval a uniquely-named copy of the new code into memory.
		// Only meaningful in auto-rebuild mode; production never reaches here.
		if ($this->autoRebuild && $codeRegenerated) {
			return $this->reloadAsUnique($class, $file);
		}

		// Class loaded and code unchanged (only meta refreshed, or no rebuild needed at all).
		return $class;
	}


	/**
	 * Loads a regenerated container file under a fresh, unique class name via eval(),
	 * sidestepping PHP's "Cannot redeclare class" restriction in long-running processes.
	 * @return class-string<Container>
	 */
	private function reloadAsUnique(string $class, string $file): string
	{
		$unique = $class . '_R' . substr(bin2hex(random_bytes(4)), 0, 8);
		$code = @file_get_contents($file); // @ - file may have been deleted between write and read
		if ($code === false) {
			throw new Nette\IOException(sprintf("Unable to read '%s' for live reload.", $file));
		}

		$count = 0;
		$code = preg_replace(
			'~\bclass\s+' . preg_quote($class, '~') . '\b~',
			"class $unique",
			$code,
			limit: 1,
			count: $count,
		);
		if ($code === null || $count !== 1) {
			throw new Nette\InvalidStateException(sprintf("Unable to rename class '%s' for live reload (expected 1 replacement, got %d).", $class, $count));
		}

		// Strip the leading <?php so the code can be eval'd.
		$code = preg_replace('~^\s*<\?php\s*~', '', $code, limit: 1);
		eval($code);

		if (!class_exists($unique, autoload: false)) {
			throw new Nette\InvalidStateException(sprintf("Live reload eval failed: class '%s' was not defined.", $unique));
		}

		return $unique;
	}


	private function isExpired(string $file, ?string &$updatedMeta = null): bool
	{
		if ($this->autoRebuild) {
			$meta = @unserialize((string) file_get_contents("$file.meta")); // @ - file may not exist
			$orig = $meta[2] ?? null;
			return empty($meta[0])
				|| DependencyChecker::isExpired(...$meta)
				|| ($orig !== $meta[2] && $updatedMeta = serialize($meta));
		}

		return false;
	}


	/**
	 * @param  callable(Compiler): ?string  $generator
	 * @return array{string, string} code, file
	 */
	protected function generate(string $class, callable $generator): array
	{
		$compiler = new Compiler;
		$compiler->setClassName($class);
		$code = $generator(...[&$compiler]) ?? $compiler->compile();
		return [
			"<?php\n$code",
			serialize($compiler->exportDependencies()),
		];
	}
}
